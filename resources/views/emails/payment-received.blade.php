<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1a1a1a; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-width: 150px; }
        .content { margin-bottom: 30px; }
        .status-box { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 5px; font-weight: bold; text-align: center; }
        .button { display: inline-block; padding: 12px 24px; background-color: #0d47a1; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { font-size: 12px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng" class="logo">
            <h2 style="color: #0d47a1;">Payment Confirmed!</h2>
        </div>
        <div class="content">
            <p>Hello {{ $employer_name }},</p>
            <p>We've successfully received your payment for the helper matching fee or booking deposit.</p>
            
            <div class="status-box">
                Transaction Successful: ₦{{ number_format($amount) }}
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p><strong>Payment Reference:</strong> {{ $reference }}</p>
                <p><strong>Item:</strong> {{ $concept }}</p>
                <p><strong>Date:</strong> {{ $date }}</p>
            </div>

            <p>Your transaction has been secured by the Treasurer AI Agent. You can now proceed with your helper onboarding.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/employer/dashboard') }}" class="button">Go to Dashboard</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Maids.ng. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
