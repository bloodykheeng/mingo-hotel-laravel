<!-- resources/views/emails/welcome.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Mingo Hotel</title>
    <style>
        /* Reset styles */
        body,
        html {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }

        /* Container styles */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }

        /* Header styles */
        .header {
            background-color: #ffb347;
            /* A warm, welcoming orange */
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
        }

        /* Content styles */
        .content {
            padding: 30px 20px;
            background-color: #fff;
            border-left: 1px solid #e6e6e6;
            border-right: 1px solid #e6e6e6;
        }

        .welcome-message {
            font-size: 18px;
            margin-bottom: 20px;
        }

        .highlight {
            color: #ffb347;
            font-weight: bold;
        }

        /* Button styles */
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #ffb347;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            margin: 20px 0;
        }

        /* Footer styles */
        .footer {
            background-color: #333;
            color: #ffffff;
            text-align: center;
            padding: 15px;
            font-size: 14px;
            border-radius: 0 0 5px 5px;
        }

        .footer p {
            margin: 5px 0;
        }

        .social-links {
            margin: 15px 0;
        }

        .social-links a {
            color: #ffffff;
            margin: 0 10px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Mingo Hotel</h1>
        </div>

        <div class="content">
            <p class="welcome-message">Hello <span class="highlight">{{ $user->name }}</span>,</p>

            <p>Thank you for registering with Mingo Hotel! We're thrilled to have you join our community.</p>

            <p>At Mingo Hotel, we're committed to providing exceptional service and making your stay as comfortable and
                enjoyable as possible. Our dedicated staff is ready to assist you with anything you need.</p>

            <p>Here's what you can look forward to:</p>
            <ul>
                <li>Personalized service tailored to your preferences</li>
                <li>Luxurious accommodations with modern amenities</li>
                <li>Exquisite dining experiences</li>
                <li>A range of activities and entertainment options</li>
            </ul>

            <p>If you have any questions or special requests, please don't hesitate to contact us. We're here to ensure
                your experience with us exceeds your expectations.</p>

            <p>We look forward to serving you soon!</p>

            {{-- <a href="https://mingohotel.com/my-account" class="button">Visit Your Account</a> --}}

            <p>Warm regards,<br>
                The Mingo Hotel Team</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Mingo Hotel. All rights reserved.</p>
            <p>123 Hospitality Lane, Paradise City</p>
            <div class="social-links">
                <a href="#">Facebook</a> | <a href="#">Instagram</a> | <a href="#">Twitter</a>
            </div>
            <p><small>If you didn't register for an account, please disregard this email.</small></p>
        </div>
    </div>
</body>

</html>
