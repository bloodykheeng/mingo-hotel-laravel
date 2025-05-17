<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Room Booking - Mingo Hotel Kayunga</title>
    <style>
        /* Email Styles */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background-color: #4A6741;
            /* Earthy green color */
            padding: 20px;
            text-align: center;
        }

        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .email-content {
            padding: 30px;
        }

        .email-footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #777777;
        }

        .booking-details {
            background-color: #f9f9f9;
            border-left: 4px solid #4A6741;
            padding: 15px;
            margin-bottom: 20px;
        }

        .detail-row {
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 140px;
        }

        .button {
            display: inline-block;
            background-color: #4A6741;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .button:hover {
            background-color: #3d5635;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h1>New Room Booking Notification</h1>
        </div>

        <div class="email-content">
            <p>Dear {{ $admin->name }},</p>

            <p>A new room has been booked at Mingo Hotel Kayunga by the following client:</p>

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Client Name:</span>
                    <span>{{ $client->name }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span>{{ $client->email }}</span>
                </div>

                @if (isset($client->phone))
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span>{{ $client->phone }}</span>
                    </div>
                @endif
            </div>

            <p><strong>Booking Details:</strong></p>

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span>{{ $room->name }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Check-in Date:</span>
                    <span>{{ date('F j, Y', strtotime($roomBooking->check_in)) }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Check-out Date:</span>
                    <span>{{ date('F j, Y', strtotime($roomBooking->check_out)) }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span>{{ \Carbon\Carbon::parse($roomBooking->check_in)->diffInDays(\Carbon\Carbon::parse($roomBooking->check_out)) }}
                        nights</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Adults:</span>
                    <span>{{ $roomBooking->number_of_adults }}</span>
                </div>

                @if ($roomBooking->number_of_children > 0)
                    <div class="detail-row">
                        <span class="detail-label">Children:</span>
                        <span>{{ $roomBooking->number_of_children }}</span>
                    </div>
                @endif

                @if ($roomBooking->description)
                    <div class="detail-row">
                        <span class="detail-label">Special Requests:</span>
                        <span>{{ $roomBooking->description }}</span>
                    </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span>{{ $roomBooking->id }}</span>
                </div>
            </div>

            <p>You can view the complete booking details in the admin dashboard.</p>

            {{-- <a href="{{ url('/admin/room-bookings/' . $roomBooking->id) }}" class="button">View Booking Details</a> --}}
        </div>

        <div class="email-footer">
            <p>&copy; {{ date('Y') }} Mingo Hotel Kayunga. All rights reserved.</p>
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>

</html>
