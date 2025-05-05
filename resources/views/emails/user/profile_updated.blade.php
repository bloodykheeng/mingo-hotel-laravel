<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Profile Updated</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f8fb;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-top: 6px solid #004ba0;
            /* PPDA Blue */
        }

        .header {
            background-color: #004ba0;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 30px;
        }

        h2 {
            color: #004ba0;
        }

        p {
            line-height: 1.6;
        }

        .footer {
            margin-top: 30px;
            font-size: 0.9em;
            color: #777;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>PPDA Uganda</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $user->name }},</h2>

            <p>Your profile has been <strong>successfully updated</strong>.</p>

            <p>If you did not make this change, please contact our support team immediately for assistance.</p>

            <p class="footer">
                Best regards,<br>
                Mingo Hotel Support Team
            </p>
        </div>
    </div>
</body>

</html>
