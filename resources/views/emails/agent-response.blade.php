<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maids.ng</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            border-bottom: 2px solid #10b981;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .header h1 {
            color: #10b981;
            font-size: 24px;
            margin: 0;
        }

        .content {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .footer {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
        }

        .btn {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Maids.ng</h1>
    </div>

    <div class="content">
        {!! nl2br(e($body)) !!}
    </div>

    <div class="footer">
        <p>This is an automated response from the Maids.ng AI Assistant.</p>
        <p>Need further help? Visit <a href="https://maids.ng">maids.ng</a> or reply to this email.</p>
    </div>
</body>

</html>