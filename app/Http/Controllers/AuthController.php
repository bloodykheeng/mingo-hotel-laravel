<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{

    use Loggable;

    public function register(Request $request)
    {

        // Convert string "true"/"false" to actual boolean values
        $request->merge([
            'agree'               => filter_var($request->input('agree'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'allow_notifications' => filter_var($request->input('allow_notifications'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);

        // Validate request data
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'email'                  => 'required|string|email|max:255|unique:users',
            'password'               => 'required|string|min:8',
            'status'                 => 'required|string|max:255',
            'role'                   => 'required|exists:roles,name',
            'phone'                  => 'required|string|unique:users|regex:/^\+\d{12}$/',
            'agree'                  => 'required|boolean',
            'gender'                 => 'nullable|string|max:10',
            'nationality'            => 'nullable|string|max:100',
            'age'                    => 'nullable|integer|min:0|max:150',
            'date_of_birth'          => 'nullable|date',
            'group_code'             => 'nullable|string|max:255',
            'registration_channel'   => 'nullable|string|max:255',
            'allow_notifications'    => 'nullable|boolean',
            'device_token'           => 'nullable|string',
            'web_app_firebase_token' => 'nullable|string',
            'photo'                  => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        DB::beginTransaction();

        try {
            // Check if role exists
            $role = $validated['role'];
            if (! Role::where('name', $role)->exists()) {
                return response()->json(['message' => 'Role does not exist'], 400);
            }

            // Handle photo upload
            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $photoUrl = $this->uploadPhoto($request->file('photo'), 'user_photos');
            }

            // Create user
            $user = User::create([
                'name'                   => $validated['name'],
                'email'                  => $validated['email'],
                'status'                 => $validated['status'],
                'password'               => Hash::make($validated['password']),
                'phone'                  => $validated['phone'],
                'gender'                 => $validated['gender'] ?? null,
                'nationality'            => $validated['nationality'] ?? null,
                'age'                    => $validated['age'] ?? null,
                'date_of_birth'          => $validated['date_of_birth'] ?? null,
                'agree'                  => $validated['agree'],
                'registration_channel'   => $validated['registration_channel'] ?? 'app',
                'allow_notifications'    => $validated['allow_notifications'] ?? true,
                'device_token'           => $validated['device_token'] ?? null,
                'web_app_firebase_token' => $validated['web_app_firebase_token'] ?? null,
                'photo'                  => $photoUrl,
                'lastlogin'              => now(),
                'role'                   => $role,
            ]);

            $user->update([
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Assign role
            $user->syncRoles([$role]);

            // Log activity
            $this->logActivity(
                'registration',
                "New user registration: '{$user->name}' with role '{$role}' and email '{$user->email}' was created.",
                [
                    'user_id' => $user->id,
                    'role'    => $role,
                    'name'    => $user->name,
                ]
            );

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message'      => 'User successfully registered',
                'data'         => $user,
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle photo upload.
     */
    private function uploadPhoto($photo, $folderPath)
    {
        $publicPath = public_path($folderPath);
        if (! File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0777, true, true);
        }

        $fileName = time() . '_' . $photo->getClientOriginalName();
        $photo->move($publicPath, $fileName);

        return '/' . $folderPath . '/' . $fileName;
    }

    public function checkLoginStatus(Request $request)
    {
        // Check if the user is logged in
        if (! Auth::check()) {
            return response()->json(['message' => 'User is not logged in'], 401);
        }

        // /** @var \App\Models\User */
        // $user = Auth::user();

        // Load user with related models
        $user = User::with([
            'regionalOffice',
            'cso',
        ])
            ->where('id', Auth::user()->id)->first();

        // Check if the user was found
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

                                                      // Retrieve the token
        $token = $user->tokens->first()->token ?? ''; // Adjusted to handle potential null value

        // Get the photo URL, fallback to third-party provider's photo URL if not set
        $photoUrl = $user->photo_url;
        if (empty($photoUrl)) {
            $photoUrl = $user->providers()->first()->photo_url ?? null;
        }

        $response = [
            'message'              => 'Hi ' . $user->name . ', welcome to home',
            'id'                   => $user->id,
            // 'access_token'            => $token,
            // 'token_type'              => 'Bearer',
            'name'                 => $user->name,
            'lastlogin'            => $user->lastlogin,
            'email'                => $user->email,
            'gender'               => $user->gender,
            'citizenship'          => $user->citizenship,
            'status'               => $user->status,
            'registration_channel' => $user->registration_channel,
            'allow_notifications'  => $user->allow_notifications,
            'photo_url'            => $photoUrl,
            'permissions'          => $user->getAllPermissions()->pluck('name'), // pluck for simplified array
            'role'                 => $user->role,
            'phone'                => $user->phone,
            'date_of_birth'        => $user->date_of_birth,
            'agree'                => $user->agree,
            'regional_office'      => $user->regionalOffice,
            'cso'                  => $user->cso,
        ];

        return response()->json($response);
    }

    //  ====================================== dashboard login ==============================================
    public function login(Request $request)
    {

        try {
            // Validate the login request
            $request->validate([
                'email'    => 'required|string|email',
                'password' => 'required|string',
            ]);

            // normal credentials
            $credentials = $request->only('email', 'password');

            // First, attempt local database login with the default guard
            if (! Auth::attempt($credentials)) {
                return response()->json(['message' => 'Invalid Email or Password'], 401);
            }

            // Get the user based on their email
            $user = User::with([
                'regionalOffice',
                'cso',
            ])->where('email', $request['email'])->first();

            // Check if the user exists
            if (! isset($user)) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Check if the user's status is active
            if ($user->status !== 'active') {
                return response()->json(['message' => 'Account is not active'], 403);
            }

            // Define the accepted roles
            $acceptedRoles = [
                'System Admin',
                'PPDA Admin',
                'CSO Admin',
                'PPDA Officer',
                'CSO Approver',
                'CSO Verifier',
                'CSO Monitor',
            ];

            // Get the user's role
            $userRole = $user->role;

            // Check if the user's role is in the accepted roles list
            if (! in_array($userRole, $acceptedRoles)) {
                return response()->json(['message' => 'Unauthorized role'], 403);
            }

            // Update the lastlogin field with the current timestamp
            $user->update(['lastlogin' => now()]);

            // Generate a token for the user
            $token = $user->createToken('auth_token')->plainTextToken;

            // Build the response
            $response = [
                'message'              => 'Hi ' . $user->name . ', welcome to home',
                'id'                   => $user->id,
                'access_token'         => $token,
                'token_type'           => 'Bearer',
                'name'                 => $user->name,
                'photo_url'            => $user->photo_url,
                'lastlogin'            => $user->lastlogin,
                'email'                => $user->email,
                'gender'               => $user->gender,
                'status'               => $user->status,
                'registration_channel' => $user->registration_channel,
                'allow_notifications'  => $user->allow_notifications,
                'permissions'          => $user->getAllPermissions()->pluck('name') ?? [],
                'role'                 => $userRole ?? "",
                'phone'                => $user->phone,
                'agree'                => $user->agree,
                'regional_office'      => $user->regionalOffice,
                'cso'                  => $user->cso,

            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while signig in', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        // /** @var \App\Models\User */
        // // $user = Auth::user(); // Get the authenticated user
        // $user = Auth::guard('web')->user();

        // // Revoke all tokens...
        // $user->tokens()->delete();

        // delete the current token that was used for the request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

}