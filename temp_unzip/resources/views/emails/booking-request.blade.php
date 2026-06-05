<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1a1a1a; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-width: 150px; }
        .content { margin-bottom: 30px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #0d47a1; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { font-size: 12px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng" class="logo">
            <h2 style="color: #0d47a1;">New Booking Request Received!</h2>
        </div>
        <div class="content">
            <p>Hello {{ $maid_name }},</p>
            <p>An employer on Maids.ng has specifically requested your services. This is a testament to your excellent profile and skills!</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p><strong>Employer:</strong> {{ $employer_name }}</p>
                <p><strong>Start Date:</strong> {{ $start_date }}</p>
                <p><strong>Schedule:</strong> {{ $schedule_type }}</p>
                <p><strong>Agreed Salary:</strong> ₦{{ number_format($salary) }}</p>
            </div>

            <p>Please log in to your dashboard to review the details and accept the booking.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/maid/bookings') }}" class="button">View Booking Details</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Maids.ng. Nigeria's Most Trusted Helper Marketplace.</p>
            <p>This message was sent by our automated Neural Mission Control system.</p>
        </div>
    </div>
</body>
</html>
