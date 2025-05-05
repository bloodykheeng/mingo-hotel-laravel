<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f1f1f1;
        }

        .header {
            background-color: #0043f9;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .form-container {
            max-width: 500px;
            margin: 40px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 38px;
            cursor: pointer;
            z-index: 10;
        }

        .form-group {
            position: relative;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Confidential</h1>
        <h4>PPDA CMS</h4>
    </div>

    <div class="form-container">
        <!-- Laravel validation errors display -->
        @if (isset($error))
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <li>{{ $error }}</li>
                </ul>
            </div>
        @endif

        @if (isset($validator) && $validator->fails())
            <ul class="alert alert-danger">
                @foreach ($validator->errors()->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="post" action="/api/reset-password" onsubmit="return validateForm()">
            <!-- CSRF Token -->

            <div class="mb-3 position-relative">
                <label for="password" class="form-label">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <i class="bi bi-eye-slash toggle-password" onclick="toggleVisibility('password', this)"></i>
                <div id="password-error" class="text-danger mt-1" style="display: none;"></div>
            </div>

            <div class="mb-3 position-relative">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                    required>
                <i class="bi bi-eye-slash toggle-password"
                    onclick="toggleVisibility('password_confirmation', this)"></i>
                <div id="confirm-error" class="text-danger mt-1" style="display: none;"></div>
            </div>


            <input type="hidden" name="id" value="{{ $user->id }}">
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="submit" class="btn btn-primary w-100" value="Reset Password">
        </form>

    </div>


    <script>
        // // Test if JavaScript is loading
        // console.log("JavaScript is loaded and running");

        function toggleVisibility(id, icon) {
            console.log("Toggle function called for", id);
            const input = document.getElementById(id);
            const isPassword = input.type === 'password';

            // Toggle input type
            input.type = isPassword ? 'text' : 'password';

            // Toggle icon class manually
            if (isPassword) {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }

        function validateForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirmation').value;
            const passwordError = document.getElementById('password-error');
            const confirmError = document.getElementById('confirm-error');

            passwordError.style.display = "none";
            confirmError.style.display = "none";

            if (password !== confirm) {
                confirmError.innerText = "Passwords do not match.";
                confirmError.style.display = "block";
                return false;
            }

            const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

            if (!passwordPattern.test(password)) {
                passwordError.innerText =
                    "Password must be at least 8 characters and include uppercase, lowercase, number, and special character (! @ # $ % ^ & * ( ) _ + - = { } [ ] | \\ : ; ' < > , . ? /).";
                passwordError.style.display = "block";
                return false;
            }



            return true;
        }
    </script>

</body>

</html>
