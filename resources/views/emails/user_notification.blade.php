<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Report Accepted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
        }

        .header {
            background-color: #4CAF50;
            color: #ffffff;
            padding: 10px;
            text-align: center;
        }

        .content {
            padding: 20px 0;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8em;
            color: #777;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Notification</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $user->name }},</h2>
            <p>You have a new notification:</p>
            <div class="message">
                <p>{{ $notificationMessage }}</p>
            </div>
            <p>Thank You.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Citizen Feedback Platform. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
