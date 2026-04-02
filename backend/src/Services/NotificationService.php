<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

class NotificationService
{
    private EmailService $emailService;
    private SmsService $smsService;
    private LoggerInterface $logger;

    public function __construct(EmailService $emailService, SmsService $smsService, LoggerInterface $logger)
    {
        $this->emailService = $emailService;
        $this->smsService = $smsService;
        $this->logger = $logger;
    }

    public function notifyBookingCreated(array $booking, array $helper, array $employer): void
    {
        $this->logger->info("Internal Notification: Booking Created", [
            'reference' => $booking['reference'],
            'employer' => $employer['full_name'] ?? 'Unknown',
            'helper' => $helper['full_name'] ?? 'Unknown'
        ]);

        // 1. Notify Employer
        if (!empty($employer['email'])) {
            $this->emailService->sendBookingConfirmation($booking, $helper, $employer['email']);
        }

        if (!empty($employer['phone'])) {
            $this->smsService->sendBookingConfirmation($employer['phone'], $booking, $helper);
        }

        // 2. Notify Helper
        if (!empty($helper['email'])) {
            $this->emailService->sendNewBookingNotification($booking, $helper, $employer);
        }

        if (!empty($helper['phone'])) {
            $this->smsService->sendNewBookingToHelper(
                $helper['phone'],
                $helper['full_name'],
                $employer['location'] ?? 'Nigeria'
            );
        }
    }

    public function notifyPaymentSuccess(array $payment, array $booking, array $helper, array $employer): void
    {
        $this->logger->info("Internal Notification: Payment Success", [
            'tx_ref' => $payment['tx_ref'],
            'amount' => $payment['amount']
        ]);

        // 1. Notify Employer (Receipt)
        if (!empty($employer['email'])) {
            $this->emailService->sendPaymentReceipt($payment, $booking, $employer['email']);
        }

        if (!empty($employer['phone'])) {
            $this->smsService->sendPaymentConfirmation($employer['phone'], $payment);
        }

        // 2. Welcome Email if it's their first successful payment? 
        // (Optional: can be handled elsewhere)
    }

    public function notifyPaymentFailed(array $payment, array $booking, array $employer): void
    {
        $this->logger->warning("Internal Notification: Payment Failed", [
            'tx_ref' => $payment['tx_ref'],
            'employer' => $employer['full_name'] ?? 'Unknown'
        ]);

        if (!empty($employer['email'])) {
            $subject = "Payment Failed - Maids.ng";
            $message = "Your payment of " . ($payment['currency'] ?? 'NGN') . " " . number_format($payment['amount'], 2) . " for booking " . $booking['reference'] . " failed. Please try again or contact support if the issue persists.";
            $this->emailService->send($employer['email'], $subject, $message);
        }

        if (!empty($employer['phone'])) {
            $sms = "Maids.ng: Payment for booking " . $booking['reference'] . " failed. Please try again or contact support.";
            $this->smsService->send($employer['phone'], $sms);
        }
    }

    public function notifyHelperVerified(array $helper): void
    {
        $this->logger->info("Internal Notification: Helper Verified", ['helper_id' => $helper['id']]);

        $this->emailService->sendVerificationStatus($helper, 'verified');

        if (!empty($helper['phone'])) {
            $this->smsService->sendVerificationStatus($helper['phone'], $helper['full_name'], true);
        }
    }

    public function notifyVerificationRejected(array $helper, string $reason): void
    {
        $this->logger->info("Internal Notification: Verification Rejected", [
            'helper_id' => $helper['id'],
            'reason' => $reason
        ]);

        $this->emailService->sendVerificationStatus($helper, 'rejected', $reason);

        if (!empty($helper['phone'])) {
            $this->smsService->sendVerificationStatus($helper['phone'], $helper['full_name'], false);
        }
    }

    public function notifyNewLead(array $lead): void
    {
        $this->logger->info("Internal Notification: New Lead Captured", $lead);

        // 1. Notify Admin via Email
        $this->emailService->sendNewLeadNotification($lead);

        // 2. Notify Admin via SMS
        $this->smsService->sendNewLeadAlert($lead);
    }

    public function notifyPendingRequest(array $request): void
    {
        $this->logger->info('Internal Notification: Pending Service Request', $request);

        // Notify admin via email
        $subject = 'New Pending Maid Request – Action Required';
        $name = !empty($request['full_name']) ? $request['full_name'] : 'Unknown';
        $body = "A new maid request has been logged with no available match.\n\n"
            . "Reference ID : #{$request['id']}\n"
            . "Client Phone : {$request['phone']}\n"
            . "Client Name  : {$name}\n"
            . "Help Type    : {$request['help_type']}\n"
            . "Location     : {$request['location']}\n"
            . "New User     : " . ($request['is_new_user'] ? 'Yes' : 'No') . "\n\n"
            . "Please log into the admin dashboard to follow up.";

        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;
        if ($adminEmail) {
            $this->emailService->send($adminEmail, $subject, $body);
        }

        // Notify admin via SMS
        $adminPhone = $_ENV['ADMIN_PHONE'] ?? null;
        if ($adminPhone) {
            $sms = "Maids.ng: New pending request #{$request['id']} from {$request['phone']} ({$request['help_type']} in {$request['location']}). No match found. Check dashboard.";
            $this->smsService->send($adminPhone, $sms);
        }
    }

    public function notifyNewRating(array $rating, array $helper, array $employer): void
    {
        $this->logger->info("Internal Notification: New Rating Received", [
            'rating' => $rating['rating'],
            'helper' => $helper['full_name'],
            'employer' => $employer['full_name']
        ]);

        // Could notify helper that they received a new rating
        if (!empty($helper['email'])) {
            $subject = "You received a new rating! | Maids.ng";
            $message = "Congratulations! " . $employer['full_name'] . " just gave you a " . $rating['rating'] . "-star rating. Keep it up!";
            $this->emailService->send($helper['email'], $subject, $message);
        }
    }

    public function notifyBookingAccepted(array $booking, array $helper, array $employer): void
    {
        $this->logger->info("Internal Notification: Booking Accepted", [
            'reference' => $booking['reference'],
            'helper' => $helper['full_name'] ?? 'Unknown',
            'employer' => $employer['full_name'] ?? 'Unknown'
        ]);

        // Notify employer
        if (!empty($employer['email'])) {
            $subject = 'Booking Accepted – Next Step';
            $message = "Good news! Your booking (Ref: {$booking['reference']}) has been accepted by {$helper['full_name']}. Please proceed to payment to secure the booking.";
            $this->emailService->send($employer['email'], $subject, $message);
        }

        if (!empty($employer['phone'])) {
            $sms = "Maids.ng: Booking {$booking['reference']} accepted. Log in to complete payment. Thank you!";
            $this->smsService->send($employer['phone'], $sms);
        }
    }

    public function notifyBookingRejected(array $booking, array $helper, array $employer): void
    {
        $this->logger->info("Internal Notification: Booking Rejected", [
            'reference' => $booking['reference'],
            'helper' => $helper['full_name'] ?? 'Unknown',
            'employer' => $employer['full_name'] ?? 'Unknown'
        ]);

        // Notify employer
        if (!empty($employer['email'])) {
            $subject = 'Booking Declined';
            $message = "We regret to inform you that your booking (Ref: {$booking['reference']}) for {$helper['full_name']} has been declined. Please search for another helper on Maids.ng.";
            $this->emailService->send($employer['email'], $subject, $message);
        }

        if (!empty($employer['phone'])) {
            $sms = "Maids.ng: Booking {$booking['reference']} declined. Please try again or search for another helper.";
            $this->smsService->send($employer['phone'], $sms);
        }
    }

    public function notifyBookingApprovedByAgency(array $booking, array $employer): void
    {
        $this->logger->info("Internal Notification: Booking Approved by Agency", [
            'reference' => $booking['reference']
        ]);

        // Notify employer
        if (!empty($employer['email'])) {
            $subject = 'Booking Approved by Agency';
            $message = "Your booking (Ref: {$booking['reference']}) has been approved by the agency. Please check your dashboard for next steps.";
            $this->emailService->send($employer['email'], $subject, $message);
        }

        if (!empty($employer['phone'])) {
            $sms = "Maids.ng: Agency approved your booking {$booking['reference']}. Proceed to payment.";
            $this->smsService->send($employer['phone'], $sms);
        }
    }
}

