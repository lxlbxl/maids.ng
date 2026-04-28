<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f9f7f4; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .container { background: #ffffff; border: 1px solid #e5e2dd; border-radius: 16px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #0d9488, #0f766e); text-align: center; padding: 40px 30px; }
        .header img { max-width: 120px; margin-bottom: 16px; }
        .header h1 { color: #ffffff; font-size: 24px; font-weight: 300; margin: 0; }
        .header h1 em { font-style: italic; color: #fbbf24; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 18px; color: #1c1917; margin-bottom: 16px; }
        .message { color: #57534e; font-size: 14px; margin-bottom: 24px; }
        .credential-box { background: #f5f3f0; border: 1px solid #e5e2dd; border-radius: 10px; padding: 20px; margin: 24px 0; }
        .credential-box p { margin: 6px 0; font-size: 14px; color: #44403c; }
        .credential-box strong { color: #1c1917; }
        .credential-box .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #78716c; font-weight: 600; }
        .tips { margin: 24px 0; }
        .tips h3 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #0d9488; margin-bottom: 12px; }
        .tip-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #57534e; }
        .tip-icon { font-size: 16px; flex-shrink: 0; }
        .button-wrapper { text-align: center; margin: 30px 0; }
        .button { display: inline-block; padding: 14px 32px; background-color: #0d9488; color: #ffffff !important; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 14px; }
        .divider { height: 1px; background: #e5e2dd; margin: 24px 0; }
        .footer { text-align: center; padding: 24px 30px; background: #fafaf9; }
        .footer p { font-size: 11px; color: #a8a29e; margin: 4px 0; }
        .change-password { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #92400e; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng">
                <h1>Welcome to <em>Maids.ng</em></h1>
            </div>
            <div class="content">
                <p class="greeting">Hello {{ $user->name }},</p>
                <p class="message">
                    Your account has been created successfully! You're now part of Nigeria's most trusted 
                    platform for finding verified household helpers. We're excited to help you find the perfect match.
                </p>

                <div class="credential-box">
                    <p class="label">Your Login Details</p>
                    <p><strong>Email:</strong> {{ $user->email }}</p>
                    @if($tempPassword)
                        <p><strong>Temporary Password:</strong> {{ $tempPassword }}</p>
                    @endif
                    @if($user->phone)
                        <p><strong>Phone:</strong> {{ $user->phone }}</p>
                    @endif
                </div>

                @if($tempPassword)
                    <div class="change-password">
                        🔐 We've set a temporary password for your account. Please change it after your first login for security.
                    </div>
                @endif

                <div class="tips">
                    <h3>What Happens Next</h3>
                    <div class="tip-item">
                        <span class="tip-icon">🔍</span>
                        <span>We're searching for helpers that match your preferences right now.</span>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">✅</span>
                        <span>All our helpers undergo background verification for your safety.</span>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">💬</span>
                        <span>You can log in anytime to check your matches or update preferences.</span>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🛡️</span>
                        <span>Our support team is available to assist you every step of the way.</span>
                    </div>
                </div>

                <div class="button-wrapper">
                    <a href="{{ url('/login') }}" class="button">Log In to Your Account →</a>
                </div>
            </div>
            <div class="footer">
                <p>&copy; {{ date('Y') }} Maids.ng. All rights reserved.</p>
                <p>Nigeria's Trusted Household Help Platform</p>
            </div>
        </div>
    </div>
</body>
</html>
