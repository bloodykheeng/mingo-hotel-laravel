<!-- resources/views/emails/new-user-notification.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration at Mingo Hotel</title>
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
            background-color: #4A90E2;
            /* Professional blue */
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        /* Content styles */
        .content {
            padding: 30px 20px;
            background-color: #fff;
            border-left: 1px solid #e6e6e6;
            border-right: 1px solid #e6e6e6;
        }

        .notification-message {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .highlight {
            color: #4A90E2;
            font-weight: bold;
        }

        /* User details card */
        .user-details {
            background-color: #f5f7fa;
            border-left: 4px solid #4A90E2;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }

        .user-details h3 {
            margin-top: 0;
            color: #4A90E2;
        }

        .detail-row {
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        /* Button styles */
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4A90E2;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>New User Registration</h1>
        </div>

        <div class="content">
            <p class="notification-message">Hello <span class="highlight">{{ $admin->name }}</span>,</p>

            <p>This is an automated notification to inform you that a new user has registered at Mingo Hotel.</p>

            <div class="user-details">
                <h3>User Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Name:</span> {{ $newUser->name }}
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span> {{ $newUser->email }}
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span> {{ $newUser->phone }}
                </div>
                <div class="detail-row">
                    <span class="detail-label">Role:</span> {{ $newUser->role }}
                </div>
                <div class="detail-row">
                    <span class="detail-label">Registration:</span> {{ $newUser->created_at->format('M d, Y H:i') }}
                </div>
                @if ($newUser->nationality)
                    <div class="detail-row">
                        <span class="detail-label">Nationality:</span> {{ $newUser->nationality }}
                    </div>
                @endif
                @if ($newUser->gender)
                    <div class="detail-row">
                        <span class="detail-label">Gender:</span> {{ $newUser->gender }}
                    </div>
                @endif
                @if ($newUser->date_of_birth)
                    <div class="detail-row">
                        <span class="detail-label">Date of Birth:</span>
                        {{ \Carbon\Carbon::parse($newUser->date_of_birth)->format('M d, Y') }}
                    </div>
                @endif
            </div>

            <p>You can review this registration and take any necessary actions by clicking the button below:</p>

            {{-- <a href="{{ url('/admin/users/' . $newUser->id) }}" class="button">View User Profile</a> --}}

            <p>Thank you for your attention to this matter.</p>

            <p>Best regards,<br>
                Mingo Hotel Automated System</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Mingo Hotel. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>

</html>
