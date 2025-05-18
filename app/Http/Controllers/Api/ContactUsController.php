<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends Controller
{
    use Loggable;

    /**
     * Send contact us notification to system admins
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendContactUsNotification(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name'    => 'required|string|min:3|max:100',
            'email'   => 'required|email|max:100',
            'phone'   => 'required|string|min:10|max:20',
            'message' => 'required|string|min:10',
        ]);

        // Get all system admins
        $systemAdmins = User::where('role', 'System Admin')->get();

        if ($systemAdmins->isEmpty()) {
            $this->logActivity(
                'email_send_skipped',
                'No system admins found to send contact notification to.',
                ['contact_email' => $validated['email']]
            );

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting Mingo Hotel Kayunga. We will get back to you shortly.',
            ], 200);
        }

        try {
            foreach ($systemAdmins as $admin) {
                Mail::send('emails.contact.contact_notification', [
                    'name'       => $validated['name'],
                    'email'      => $validated['email'],
                    'phone'      => $validated['phone'],
                    'message'    => $validated['message'],
                    'subject'    => 'New Contact Form Submission - Mingo Hotel Kayunga',
                    'hotel_name' => 'Mingo Hotel Kayunga',
                    'admin'      => $admin,
                ], function ($message) use ($admin) {
                    $message->to($admin->email)
                        ->subject('New Contact Form Submission - Mingo Hotel Kayunga');
                });
            }

            $this->logActivity(
                'email_sent',
                'Contact form notification sent to all system admins.',
                ['contact_email' => $validated['email']]
            );

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting Mingo Hotel Kayunga. We will get back to you shortly.',
            ], 200);
        } catch (\Exception $e) {
            $this->logActivity(
                'email_send_failed',
                'Failed to send contact form notification.',
                [
                    'contact_email' => $validated['email'],
                    'error_message' => $e->getMessage(),
                    'error_line'    => $e->getLine(),
                    'user_id'       => Auth::id() ?? null,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
