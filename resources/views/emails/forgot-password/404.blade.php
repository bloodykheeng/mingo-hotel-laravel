<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>PPDA CMS - 404 Not Found</title>

    <!-- Bootstrap 5 & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f1f1f1;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: #0043f9;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .logo {
            width: 120px;
            height: auto;
            display: block;
            margin: 20px auto;
        }

        .content-box {
            max-width: 500px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .footer-text {
            margin-top: 30px;
            color: #6c757d;
            font-size: 14px;
        }

        .btn-custom {
            background-color: #0043f9;
            border: none;
        }

        .btn-custom:hover {
            background-color: #0033c7;
        }

        h1 {
            font-size: 36px;
            color: #ffffff;
        }

        .icon-large {
            font-size: 50px;
            color: #dc3545;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1><i class="bi bi-shield-lock-fill me-2"></i>Confidential</h1>
        <h4>Mingo Hotel Uganda</h4>
    </div>

    <div class="content-box">
        <i class="bi bi-exclamation-triangle-fill icon-large"></i>
        <h1>404 - Not Found</h1>
        <p class="mt-3">
            This page is restricted and cannot be accessed without valid authorization.
        </p>
        <p>
            If you believe this is a mistake, please contact the Mingo Hotel Uganda support team.
        </p>

        <a href="{{ env('FRONTEND_URL') }}" class="btn btn-custom text-white mt-4">
            <i class="bi bi-box-arrow-in-left me-2"></i>Go to Login
        </a>

        <p class="footer-text mt-4">Thank you.</p>
    </div>

</body>

</html>
