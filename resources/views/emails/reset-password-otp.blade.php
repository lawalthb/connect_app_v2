<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .otp {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #eee;
            border-radius: 5px;
            letter-spacing: 5px;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset Request</h2>
        <p>Hello {{ $user->name }},</p>
        <p>We received a request to reset your password. Please use the following OTP (One-Time Password) to proceed with your password reset:</p>

        <div class="otp">{{ $otp }}</div>

        <p>This OTP will expire in 1 hour. If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>

        <p>Thank you,<br>The Connect App Team</p>
    </div>

    <div class="footer">
        <p>This is an automated message, please do not reply to this email.</p>
    </div>
</body>
</html>
