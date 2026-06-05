<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1a1a1a; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-width: 150px; }
        .content { margin-bottom: 30px; }
        .alert-box { background: #fffde7; color: #f57f17; padding: 15px; border-radius: 5px; border-left: 5px solid #fbc02d; font-weight: bold; }
        .button { display: inline-block; padding: 12px 24px; background-color: #d32f2f; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { font-size: 12px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng" class="logo">
            <h2 style="color: #d32f2f;">New Dispute Escalated</h2>
        </div>
        <div class="content">
            <div class="alert-box">
                Dispute ID: #DISP-{{ $dispute_id }}<br>
                Booking Reference: #BK-{{ $booking_id }}
            </div>

            <p>An active dispute has been filed that requires immediate administrative review.</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p><strong>Filed By:</strong> {{ $filer_name }}</p>
                <p><strong>Reason:</strong> {{ $reason }}</p>
                <p><strong>Initial AI Assessment:</strong> {{ $ai_sentiment }}</p>
            </div>

            <p>The Referee AI Agent has already captured initial statements. Please log in to Mission Control to make the final determination.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/admin/escalations') }}" class="button">Resolve Dispute</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Maids.ng Mission Control.</p>
        </div>
    </div>
</body>
</html>
