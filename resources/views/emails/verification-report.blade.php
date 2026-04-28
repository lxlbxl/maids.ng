<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; color: #2D3436; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #F0F0F0; border-radius: 12px; background: #FFFFFF; }
        .header { text-align: center; margin-bottom: 30px; padding: 20px; background: #F8F9FA; border-radius: 12px 12px 0 0; }
        .logo { max-width: 150px; }
        .content { padding: 0 20px 20px; }
        .badge { display: inline-block; padding: 6px 12px; background: #E0F2F1; color: #00796B; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .report-summary { background: #F8F9FA; border: 1px solid #E9ECEF; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .label { color: #636E72; }
        .value { font-weight: 600; color: #2D3436; }
        .button { display: inline-block; padding: 14px 28px; background-color: #008080; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; text-align: center; box-shadow: 0 4px 12px rgba(0, 128, 128, 0.2); }
        .footer { font-size: 11px; color: #95A5A6; text-align: center; border-top: 1px solid #F0F0F0; padding-top: 20px; margin-top: 30px; }
        .disclaimer { font-size: 10px; font-style: italic; color: #B2BEC3; margin-top: 15px; }
    </style>
</head>
<body style="background: #FDFCFB; padding: 40px 20px;">
    <div class="container">
        <div class="header">
            <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng" class="logo">
            <h2 style="color: #2D3436; margin-top: 15px;">Verification Report</h2>
            <div class="badge">NIN Confirmed</div>
        </div>
        
        <div class="content">
            <p>Hello {{ $verification->requester_name }},</p>
            <p>The identity verification for <strong>{{ $verification->maid_first_name }} {{ $verification->maid_last_name }}</strong> has been completed successfully.</p>
            
            <div class="report-summary">
                <div class="row">
                    <span class="label">Maid Name</span>
                    <span class="value">{{ $verification->verification_data['first_name'] ?? $verification->maid_first_name }} {{ $verification->verification_data['last_name'] ?? $verification->maid_last_name }}</span>
                </div>
                <div class="row">
                    <span class="label">NIN Number</span>
                    <span class="value">{{ substr($verification->maid_nin, 0, 3) }}xxxx{{ substr($verification->maid_nin, -3) }}</span>
                </div>
                <div class="row">
                    <span class="label">Match Score</span>
                    <span class="value" style="color: #00796B;">{{ $verification->verification_data['match_score'] ?? '100' }}%</span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value" style="color: #00796B;">Verified</span>
                </div>
            </div>

            <p>You can view and download the full detailed report, which includes the official NIMC photograph and background clearance details, using the button below:</p>
            
            <div style="text-align: center; margin: 35px 0;">
                <a href="{{ route('standalone-verification.report', $verification->id) }}" class="button">View Full Report</a>
            </div>

            <p>Thank you for choosing Maids.ng for your domestic security needs.</p>
            
            <div class="disclaimer">
                This verification was processed by the Gatekeeper AI Agent via official QoreID & NIMC channels. Reference: {{ $verification->payment_reference }}
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Maids.ng. All rights reserved.</p>
            <p>Trusted domestic help, verified and secured.</p>
        </div>
    </div>
</body>
</html>
