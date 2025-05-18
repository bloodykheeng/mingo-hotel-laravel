<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Not Available - Mingo Hotel Kayunga</title>
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
            color: #e74c3c;
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

        .alternatives {
            background-color: #ebf7ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
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
            <h1>Booking Not Available</h1>
        </div>

        <div class="content">
            <p>Dear {{ $client->name }},</p>

            <p>Thank you for your interest in staying at Mingo Hotel Kayunga. Unfortunately, we are unable to confirm
                your booking request at this time.</p>

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
            </div>

            <p>This could be due to one of the following reasons:</p>
            <ul>
                <li>The requested room is already booked for the selected dates</li>
                <li>Maintenance or renovation work scheduled during the selected period</li>
                <li>Special event or holiday restrictions</li>
            </ul>

            <div class="alternatives">
                <p><strong>What you can do next:</strong></p>
                <ul>
                    <li>Try alternative dates for your stay</li>
                    <li>Check availability of other room types</li>
                    <li>Contact our reservations team for personalized assistance</li>
                </ul>
            </div>

            <p>We value your interest in Mingo Hotel Kayunga and would be happy to help you find alternative
                accommodation options.</p>

            <p>
                <strong>Reservations Contact:</strong><br>
                Phone: +256 700 000 000<br>
                Email: reservations@mingohotelkayunga.com
            </p>

            <p>Thank you for your understanding. We hope to welcome you to Mingo Hotel Kayunga in the future.</p>

            <p>Best regards,<br>
                Mingo Hotel Kayunga Management</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Mingo Hotel Kayunga. All rights reserved.</p>
            <p>Kayunga, Uganda</p>
        </div>
    </div>
</body>

</html>
