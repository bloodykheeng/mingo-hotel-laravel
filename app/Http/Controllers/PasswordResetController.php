<?php
namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgetPassword(Request $request)
    {
        try {

            // Validate the email format
            $request->validate([
                'email' => 'required|email',
            ]);

            $user = User::where('email', $request->email)->get();
            if (count($user) > 0) {
                $token  = Str::random(40);
                $domain = URL::to('/');
                $url    = $domain . '/api/reset-password?token=' . $token;

                $data['url']   = $url;
                $data['email'] = $request->email;
                $data['title'] = 'Password Reset';
                $data['body']  = "Please click on link below to reset your password";

                Mail::send('forgot-password.forgotPasswordMail', ['data' => $data], function ($message) use ($data) {
                    $message->to($data['email'])->subject($data['title']);
                });

                $datetime = Carbon::now()->format('Y-m-d H:i:s');

                PasswordReset::updateOrCreate(['email' => $request->email], [
                    'email'   => $request->email,
                    'token'   => $token,
                    'created' => $datetime,
                ]);

                return response()->json(['success' => true, 'message' => 'Please check your mail to reset your password']);
            } else {
                return response()->json(['success' => false, 'message' => 'User Not Found']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    //     handleresetPasswordLoad
    // handlestoringNewPassword

    public function handleResetPasswordLoad(Request $request)
    {
        // return "zindazed";
        $validator = Validator::make($request->query(), [
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return view('404');
        }

        $resetData = PasswordReset::where('token', $request->query('token'))->first();

        if ($resetData !== null) {

            $user = User::where('email', $resetData['email'])->first();

            if ($user !== null) {
                return view('forgot-password.resetPasswordForm', [
                    'user'  => $user,
                    'token' => $request->token,
                ]);
            }
        }

        return view('404');
    }

    public function handlestoringNewPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
            'token'    => 'required|string',
        ]);

        $token = $request->token;

        if ($validator->fails()) {
            return View::make('forgot-password.resetPasswordForm')->with([
                'validator' => $validator,
                'user'      => (object) ['id' => $request->id],
                'token'     => $token,
            ]);
        }

        $user = User::find($request->id);

        if (! $user) {
            return View::make('forgot-password.resetPasswordForm')->with([
                'error' => 'User not found. Please try again or contact support.',
                'token' => $token, // optionally attach token to session or redirect
                'user'  => (object) ['id' => $request->id],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        PasswordReset::where('email', $user->email)->delete();

        return View::make('forgot-password.passwordResetSuccess');
    }
}