<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Contact Message</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            padding: 40px 20px;
            margin: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #1e293b;
            border-radius: 16px;
            padding: 40px;
            border: 1px solid #334155;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #334155;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #34d399;
            font-size: 24px;
            margin: 0;
        }
        .header p {
            color: #94a3b8;
            font-size: 14px;
            margin: 4px 0 0;
        }
        .field {
            margin-bottom: 20px;
        }
        .field-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .field-value {
            font-size: 16px;
            color: #f1f5f9;
            padding: 8px 12px;
            background: #0f172a;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        .message-box {
            padding: 16px;
            background: #0f172a;
            border-radius: 8px;
            border-left: 4px solid #34d399;
            color: #e2e8f0;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .badge {
            display: inline-block;
            background: #34d399;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        a {
            color: #34d399;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧠 Vumbi AI</h1>
            <p>New Contact Form Submission</p>
        </div>

        <div class="field">
            <div class="field-label">Name</div>
            <div class="field-value">{{ $data['name'] }}</div>
        </div>

        <div class="field">
            <div class="field-label">Email</div>
            <div class="field-value"><a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a></div>
        </div>

        <div class="field">
            <div class="field-label">Message</div>
            <div class="message-box">{{ $data['message'] }}</div>
        </div>

        <div class="footer">
            <span class="badge">New Lead</span>
            <p style="margin-top: 12px;">Received at {{ now()->format('F j, Y g:i A') }}</p>
            <p style="margin-top: 4px;">
                <a href="{{ route('admin.contacts') }}">View in Dashboard</a>
                &nbsp;·&nbsp;
                <a href="mailto:{{ $data['email'] }}">Reply</a>
            </p>
        </div>
    </div>
</body>
</html>