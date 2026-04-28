<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class EmailService
{
    private Client $client;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private bool $enabled;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiKey = $_ENV['SENDGRID_API_KEY'] ?? '';
        $this->fromEmail = $_ENV['EMAIL_FROM_ADDRESS'] ?? 'noreply@maids.ng';
        $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Maids.ng';
        $this->enabled = !empty($this->apiKey);

        $this->client = new Client([
            'base_uri' => 'https://api.sendgrid.com/v3/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Send an email using SendGrid
     */
    public function send(string $to, string $subject, string $htmlContent, ?string $textContent = null): bool
    {
        if (!$this->enabled) {
            $this->logger->warning('Email not sent: SendGrid API key not configured', [
                'to' => $to,
                'subject' => $subject,
            ]);
            return false;
        }

        $payload = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject,
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName,
            ],
            'content' => [],
        ];

        if ($textContent) {
            $payload['content'][] = [
                'type' => 'text/plain',
                'value' => $textContent,
            ];
        }

        $payload['content'][] = [
            'type' => 'text/html',
            'value' => $htmlContent,
        ];

        try {
            $response = $this->client->post('mail/send', [
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            $this->logger->info('Email sent', [
                'to' => $to,
                'subject' => $subject,
                'status_code' => $statusCode,
            ]);

            return $success;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking confirmation email to employer
     */
    public function sendBookingConfirmation(array $booking, array $helper, string $employerEmail): bool
    {
        $subject = "Booking Confirmed - {$helper['full_name']} | Maids.ng";

        $html = $this->renderTemplate('booking_confirmation', [
            'booking' => $booking,
            'helper' => $helper,
            'reference' => $booking['reference'],
        ]);

        return $this->send($employerEmail, $subject, $html);
    }

    /**
     * Send payment receipt email
     */
    public function sendPaymentReceipt(array $payment, array $booking, string $email): bool
    {
        $subject = "Payment Receipt - {$payment['tx_ref']} | Maids.ng";

        $html = $this->renderTemplate('payment_receipt', [
            'payment' => $payment,
            'booking' => $booking,
            'amount' => number_format($payment['amount'], 2),
            'date' => date('F j, Y', strtotime($payment['paid_at'] ?? $payment['created_at'])),
        ]);

        return $this->send($email, $subject, $html);
    }

    /**
     * Send verification status email to helper
     */
    public function sendVerificationStatus(array $helper, string $status, ?string $reason = null): bool
    {
        $email = $helper['email'] ?? null;
        if (!$email) {
            $this->logger->warning('Cannot send verification email: no email address', [
                'helper_id' => $helper['id'],
            ]);
            return false;
        }

        $isApproved = $status === 'verified';
        $subject = $isApproved
            ? "Congratulations! You're Now Verified | Maids.ng"
            : "Verification Update | Maids.ng";

        $html = $this->renderTemplate('verification_status', [
            'helper' => $helper,
            'status' => $status,
            'is_approved' => $isApproved,
            'reason' => $reason,
        ]);

        return $this->send($email, $subject, $html);
    }

    /**
     * Send PIN reset email/notification
     */
    public function sendPinReset(string $email, string $otp, string $phone): bool
    {
        $subject = "PIN Reset Request | Maids.ng";

        $html = $this->renderTemplate('pin_reset', [
            'otp' => $otp,
            'phone' => $this->maskPhone($phone),
            'expires_in' => '10 minutes',
        ]);

        return $this->send($email, $subject, $html);
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcome(string $email, string $name, string $userType): bool
    {
        $subject = "Welcome to Maids.ng!";

        $html = $this->renderTemplate('welcome', [
            'name' => $name,
            'user_type' => $userType,
            'is_helper' => $userType === 'helper',
        ]);

        return $this->send($email, $subject, $html);
    }

    /**
     * Send new booking notification to helper
     */
    public function sendNewBookingNotification(array $booking, array $helper, array $employer): void
    {
        $subject = "New Booking Alert - Maids.ng";
        $data = [
            'booking' => $booking,
            'helper' => $helper,
            'employer' => $employer
        ];

        $html = $this->renderTemplate('new_booking_helper', $data);
        $this->send($helper['email'], $subject, $html);
    }

    /**
     * Send new lead notification to admin
     */
    public function sendNewLeadNotification(array $lead): void
    {
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@maids.ng';
        $subject = "New Lead Captured - Maids.ng";

        $html = "<h3>New Lead Alert</h3>";
        $html .= "<p>A new lead has been captured on the website.</p>";
        $html .= "<ul>";
        $html .= "<li><strong>Phone:</strong> " . ($lead['phone'] ?? 'N/A') . "</li>";
        $html .= "<li><strong>Source:</strong> " . ($lead['source'] ?? 'N/A') . "</li>";
        $html .= "<li><strong>Flow:</strong> " . ($lead['flow_type'] ?? 'N/A') . "</li>";
        $html .= "<li><strong>Step reached:</strong> " . ($lead['step'] ?? 'N/A') . "</li>";
        $html .= "</ul>";

        $this->send($adminEmail, $subject, $html);
    }

    /**
     * Render an email template
     */
    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = dirname(__DIR__, 2) . "/templates/email/{$template}.html";

        if (file_exists($templatePath)) {
            $html = file_get_contents($templatePath);

            // Simple placeholder replacement
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        if (is_scalar($subValue)) {
                            $html = str_replace("{{{$key}.{$subKey}}}", htmlspecialchars((string) $subValue), $html);
                        }
                    }
                } elseif (is_scalar($value)) {
                    $html = str_replace("{{{$key}}}", htmlspecialchars((string) $value), $html);
                }
            }

            return $html;
        }

        // Fallback to inline template
        return $this->getInlineTemplate($template, $data);
    }

    /**
     * Get inline fallback template
     */
    private function getInlineTemplate(string $template, array $data): string
    {
        $baseStyles = "
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #FFD700, #98FB98); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #333; margin: 0; font-size: 24px; }
            .content { background: #fff; padding: 30px; border: 1px solid #eee; }
            .footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #FFD700; color: #333; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .highlight { background: #FFF9E6; padding: 15px; border-radius: 5px; margin: 15px 0; }
        ";

        switch ($template) {
            case 'booking_confirmation':
                return "
                    <html><head><style>{$baseStyles}</style></head><body>
                    <div class='container'>
                        <div class='header'><h1>Booking Confirmed!</h1></div>
                        <div class='content'>
                            <p>Great news! Your booking has been confirmed.</p>
                            <div class='highlight'>
                                <p><strong>Reference:</strong> {$data['reference']}</p>
                                <p><strong>Maid:</strong> {$data['helper']['full_name']}</p>
                                <p><strong>Work Type:</strong> {$data['helper']['work_type']}</p>
                            </div>
                            <p>Your maid will be in touch soon.</p>
                        </div>
                        <div class='footer'>
                            <p>Maids.ng - Connecting families with trusted domestic maids</p>
                        </div>
                    </div>
                    </body></html>
                ";

            case 'payment_receipt':
                return "
                    <html><head><style>{$baseStyles}</style></head><body>
                    <div class='container'>
                        <div class='header'><h1>Payment Receipt</h1></div>
                        <div class='content'>
                            <p>Thank you for your payment!</p>
                            <div class='highlight'>
                                <p><strong>Transaction ID:</strong> {$data['payment']['tx_ref']}</p>
                                <p><strong>Amount:</strong> ₦{$data['amount']}</p>
                                <p><strong>Date:</strong> {$data['date']}</p>
                                <p><strong>Status:</strong> Successful</p>
                            </div>
                        </div>
                        <div class='footer'>
                            <p>Maids.ng - Thank you for choosing us!</p>
                        </div>
                    </div>
                    </body></html>
                ";

            case 'verification_status':
                $statusText = $data['is_approved']
                    ? "<p style='color: green;'>Your verification has been <strong>approved</strong>! You now have a verified badge.</p>"
                    : "<p style='color: red;'>Your verification was <strong>not approved</strong>. Reason: {$data['reason']}</p>";

                return "
                    <html><head><style>{$baseStyles}</style></head><body>
                    <div class='container'>
                        <div class='header'><h1>Verification Update</h1></div>
                        <div class='content'>
                            <p>Hello {$data['helper']['full_name']},</p>
                            {$statusText}
                        </div>
                        <div class='footer'>
                            <p>Maids.ng - Building trust in domestic work</p>
                        </div>
                    </div>
                    </body></html>
                ";

            case 'pin_reset':
                return "
                    <html><head><style>{$baseStyles}</style></head><body>
                    <div class='container'>
                        <div class='header'><h1>PIN Reset</h1></div>
                        <div class='content'>
                            <p>You requested a PIN reset for your account.</p>
                            <div class='highlight' style='text-align: center;'>
                                <p style='font-size: 32px; letter-spacing: 8px; font-weight: bold;'>{$data['otp']}</p>
                                <p>This code expires in {$data['expires_in']}</p>
                            </div>
                            <p>If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>Maids.ng - Keep your account secure</p>
                        </div>
                    </div>
                    </body></html>
                ";

            case 'welcome':
                $typeMessage = $data['is_helper']
                    ? "You're now registered as a domestic maid. Complete your profile to start receiving job offers!"
                    : "You're now registered as an employer. Find your perfect domestic maid today!";

                return "
                    <html><head><style>{$baseStyles}</style></head><body>
                    <div class='container'>
                        <div class='header'><h1>Welcome to Maids.ng!</h1></div>
                        <div class='content'>
                            <p>Hello {$data['name']},</p>
                            <p>{$typeMessage}</p>
                            <p style='text-align: center; margin-top: 30px;'>
                                <a href='https://maids.ng' class='button'>Get Started</a>
                            </p>
                        </div>
                        <div class='footer'>
                            <p>Maids.ng - Trusted domestic help for Nigerian families</p>
                        </div>
                    </div>
                    </body></html>
                ";

            default:
                return "<p>Email template not found: {$template}</p>";
        }
    }

    /**
     * Mask phone number for privacy
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return $phone;
        }
        return substr($phone, 0, 4) . '****' . substr($phone, -2);
    }

    /**
     * Check if email service is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
