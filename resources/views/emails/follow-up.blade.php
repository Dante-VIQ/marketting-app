<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Inquiry - Vumbi Ventures</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #16a34a;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .fields {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e5e7eb;
        }
        .field {
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .field:last-child {
            border-bottom: none;
        }
        .field-label {
            font-weight: bold;
            color: #16a34a;
        }
        .field-description {
            color: #6b7280;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background: #16a34a;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }
        .button:hover {
            background: #15803d;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Vumbi Ventures</h1>
        <p style="margin: 5px 0 0; opacity: 0.9;">Complete Your Inquiry</p>
    </div>

    <div class="content">
        <p>Hello {{ $lead->first_name ?? 'there' }},</p>

        <p>Thank you for your interest in Vumbi Ventures! We received your inquiry and we're excited to help you with your
            @if($lead->category === 'travel')
                <strong>travel plans</strong>.
            @elseif($lead->category === 'software')
                <strong>software project</strong>.
            @else
                <strong>inquiry</strong>.
            @endif
        </p>

        <p>To provide you with the best possible service, we need a few more details:</p>

        <div class="fields">
            @foreach($missing_fields as $field)
                <div class="field">
                    <div class="field-label">🔹 {{ $field['label'] ?? $field['field'] }}</div>
                    <div class="field-description">{{ $field['question'] ?? $field['description'] ?? 'Please provide this information.' }}</div>
                </div>
            @endforeach
        </div>

        @if($content)
            <p>{{ $content }}</p>
        @endif

        <p style="margin: 30px 0;">
            <a href="{{ route('leads.update', ['lead' => $lead->id, 'token' => hash_hmac('sha256', $lead->id . $lead->email, config('app.key'))]) }}" class="button">
                📝 Update Your Inquiry
            </a>
        </p>

        <p style="font-size: 14px; color: #6b7280;">
            Or simply reply to this email with the requested information.
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">

        <p>We're looking forward to helping you!</p>

        <p style="margin-top: 20px;">
            Best regards,<br>
            <strong>The Vumbi Ventures Team</strong>
        </p>
    </div>

    <div class="footer">
        <p>Vumbi Ventures - Travel & Technology Solutions</p>
        <p>© {{ date('Y') }} Vumbi Ventures. All rights reserved.</p>
        <p>
            <small>
                If you didn't submit this inquiry, please ignore this email.
            </small>
        </p>
    </div>
</body>
</html>