<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f5;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #2b3a67;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            margin-top: 40px;
        }

        h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
        }

        p {
            color: #dcdcdc;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .code-panel {
            background-color: #f7c948;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .code {
            font-size: 22px;
            font-weight: bold;
            color: #4E6615;
            letter-spacing: 2px;
        }

        .footer {
            margin-top: 30px;
            color: #b3b3b3;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <h1 style="color: #ffffff; font-size: 24px; font-weight: bold;">We have received your request to reset your
            account password</h1>

        <p>You can use the following code to recover your account:</p>

        <div class="code-panel">
            <div class="code">{{ $code }}</div>
        </div>

        <p>The allowed duration of the code is 5 mins from the time the message was sent</p>

        <div class="footer">
            <!-- Additional footer content if needed -->
        </div>
    </div>
</body>

</html>
