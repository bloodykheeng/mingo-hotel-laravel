<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Confirmed - Mingo Hotel Kayunga</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .logo {
            max-width: 200px;
            margin: 0 auto;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .content {
            padding: 20px 0;
        }

        .booking-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .details-row {
            margin-bottom: 10px;
        }

        .label {
            font-weight: bold;
            width: 140px;
            display: inline-block;
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            font-size: 14px;
            color: #777;
            border-top: 1px solid #eaeaea;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
        }

        @media only screen and (max-width: 620px) {
            .container {
                width: 100%;
            }

            .booking-details {
                padding: 10px;
            }

            .label {
                width: 120px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Booking Confirmed!</h1>
        </div>

        <div class="content">
            <p>Dear {{ $client->name }},</p>

            <p>Great news! Your booking request at Mingo Hotel Kayunga has been <strong>confirmed</strong>.</p>

            <div class="booking-details">
                <div class="details-row">
                    <span class="label">Room:</span> {{ $room->name }}
                </div>
                <div class="details-row">
                    <span class="label">Room Type:</span> {{ $room->room_type }}
                </div>
                <div class="details-row">
                    <span class="label">Check-in:</span> {{ $roomBooking->check_in->format('D, M d, Y') }}
                </div>
                <div class="details-row">
                    <span class="label">Check-out:</span> {{ $roomBooking->check_out->format('D, M d, Y') }}
                </div>
                <div class="details-row">
                    <span class="label">Guests:</span> {{ $roomBooking->number_of_adults }}
                    Adult(s){{ $roomBooking->number_of_children ? ' + ' . $roomBooking->number_of_children . ' Child(ren)' : '' }}
                </div>
                <div class="details-row">
                    <span class="label">Price:</span> UGX {{ number_format($room->price) }} per night
                </div>
            </div>

            <p>We're excited to welcome you to Mingo Hotel Kayunga. If you have any questions or special requests before
                your arrival, please don't hesitate to contact us.</p>

            <p>
                <strong>Hotel Contact:</strong><br>
                Phone: +256 700 000 000<br>
                Email: reservations@mingohotelkayunga.com
            </p>

            <p>We look forward to providing you with an exceptional stay!</p>

            <p>Warm regards,<br>
                Mingo Hotel Kayunga Management</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Mingo Hotel Kayunga. All rights reserved.</p>
            <p>Kayunga, Uganda</p>
        </div>
    </div>
</body>

</html>
