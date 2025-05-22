<?php
namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailTestController extends Controller
{

    public function testEmail(Request $request)
    {
        // Validate the request data
        $request->validate([
            'email'   => 'required|email',
            'message' => 'required|string',
        ]);

        // Extract email and message from request
        $email       = $request->input('email');
        $messageBody = $request->input('message');

        try {
            // Send the email using the Mail facade
            Mail::send('emails.email_test', ['messageBody' => $messageBody], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email');
            });

            // Return success response
            return response()->json(['status' => 'Email sent successfully'], 200);

        } catch (Exception $e) {
            // Handle any errors that occur during email sending
            return response()->json([
                'status' => 'Email failed to send',
                'error'  => $e->getMessage(),
            ], 500);
        }
    }

}
