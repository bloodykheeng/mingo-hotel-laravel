<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }}</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .header {
            background-color: #0d6efd;
            color: #ffffff;
            padding: 1.5rem;
        }

        .logo {
            width: 150px;
            height: auto;
            margin: 1rem auto;
        }

        .content {
            max-width: 600px;
            margin: 2rem auto;
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        }

        .btn-reset {
            background-color: #0d6efd;
            color: #fff;
        }

        .footer {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="header text-center">
        <h1><i class="bi bi-shield-lock-fill me-2"></i>Confidential</h1>
        <h4>PPDA CMS</h4>
    </div>

    <div class="content text-center">
        <p class="mb-4">{{ $data['body'] }}</p>
        <a href="{{ $data['url'] }}" class="btn btn-reset btn-lg">
            <i class="bi bi-arrow-right-circle me-2"></i>Reset Your Password
        </a>
        <p class="footer mt-4">Thank You</p>
    </div>
</body>

</html>
