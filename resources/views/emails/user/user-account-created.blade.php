<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Account Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333333;
        }

        p {
            font-size: 16px;
            color: #555555;
        }

        .details {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Hello {{ $user->name }},</h2>

        <p>Your account has been successfully created.</p>

        <div class="details">
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Phone:</strong> {{ $user->phone }}</p>
            <p><strong>Password:</strong> {{ $plainPassword }}</p>
        </div>

        <p>Please log in and change your password as soon as possible for security reasons.</p>

        <p>Best regards,<br>Mingo hotel</p>
    </div>
</body>

</html>
