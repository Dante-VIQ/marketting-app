<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You're invited to {{ $brandName }} on Vumbi AI</title>
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
            padding-bottom: 24px;
            margin-bottom: 32px;
        }
        .header h1 {
            color: #34d399;
            font-size: 24px;
            margin: 0 0 4px;
        }
        .header p {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }
        .brand-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-left: 4px solid #34d399;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .brand-card .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 4px;
        }
        .brand-card .invited-by {
            font-size: 14px;
            color: #94a3b8;
        }
        .role-badge {
            display: inline-block;
            background: #34d399;
            color: #0f172a;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }
        .body-text {
            font-size: 15px;
            color: #cbd5e1;
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .cta-btn {
            display: block;
            text-align: center;
            background: #34d399;
            color: #0f172a !important;
            font-weight: 700;
            font-size: 16px;
            padding: 16px 32px;
            border-radius: 10px;
            text-decoration: none !important;
            margin-bottom: 20px;
        }
        .cta-btn:hover {
            background: #6ee7b7;
        }
        .url-fallback {
            font-size: 12px;
            color: #64748b;
            text-align: center;
            word-break: break-all;
            margin-bottom: 28px;
        }
        .url-fallback a {
            color: #34d399;
        }
        .expiry-note {
            background: #1e3a5f;
            border: 1px solid #1d4ed8;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #93c5fd;
            margin-bottom: 28px;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .footer a { color: #34d399; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <h1>🧠 Vumbi AI</h1>
            <p>AI-powered brand intelligence</p>
        </div>

        <div class="brand-card">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="invited-by">Invited by {{ $inviterName }}</div>
            <div><span class="role-badge">{{ $invitation->role }}</span></div>
        </div>

        <p class="body-text">
            <strong>{{ $inviterName }}</strong> has invited you to collaborate on
            <strong>{{ $brandName }}</strong> in Vumbi AI — an AI-powered marketing and
            brand intelligence platform. As a <strong>{{ $invitation->role }}</strong>,
            you'll have access to AI briefs, content planning, and visibility insights for this brand.
        </p>

        <a href="{{ $acceptUrl }}" class="cta-btn">Accept Invitation →</a>

        <p class="url-fallback">
            Or copy this link into your browser:<br>
            <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
        </p>

        <div class="expiry-note">
            ⏳ This invitation expires on <strong>{{ $expiresAt }}</strong>.
            If you don't have a Vumbi AI account yet, you'll be asked to create one first.
        </div>

        <div class="footer">
            <p>If you weren't expecting this invite, you can safely ignore this email.</p>
            <p style="margin-top: 8px;">
                <a href="{{ config('app.url') }}">vumbiventures.com</a>
            </p>
        </div>

    </div>
</body>
</html>
