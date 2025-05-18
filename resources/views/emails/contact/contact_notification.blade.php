<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #3B82F6;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
            background-color: #f9f9f9;
        }

        .footer {
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #666;
            background-color: #f1f1f1;
        }

        .message-box {
            background-color: white;
            border-left: 4px solid #3B82F6;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .contact-item {
            margin-bottom: 8px;
        }

        .label {
            font-weight: bold;
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>New Contact Form Submission</h1>
        </div>

        <div class="content">
            <p>Hello,{{ $admin->name }}</p>

            <p>A new contact form has been submitted on the <strong>{{ $hotel_name }}</strong> website. Please find
                the details below:</p>

            <div class="contact-info">
                <div class="contact-item">
                    <span class="label">Name:</span> {{ $name }}
                </div>
                <div class="contact-item">
                    <span class="label">Email:</span> {{ $email }}
                </div>
                <div class="contact-item">
                    <span class="label">Phone:</span> {{ $phone }}
                </div>
            </div>

            <div class="message-box">
                <div class="label">Message:</div>
                <div>{{ $message }}</div>
            </div>

            <p>Please respond to this inquiry as soon as possible.</p>

            <p>Best regards,<br>{{ $hotel_name }} Notification System</p>
        </div>

        <div class="footer">
            <p>This is an automated message from {{ $hotel_name }}. Please do not reply directly to this email.</p>
            <p>&copy; {{ date('Y') }} {{ $hotel_name }}. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
