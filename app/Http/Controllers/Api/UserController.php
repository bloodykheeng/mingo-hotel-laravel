<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use Loggable;

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {

        $query = User::with([
            'createdBy', 'updatedBy',
        ]);

        $authUser     = Auth::user() ? User::with('roles')->find(Auth::id()) : null;
        $authUserRole = $authUser?->roles->pluck('name')->last();

        // Handle role filtering (string or array)
        if ($request->filled('roles')) {
            $requestedRoles = $request->query('roles');
            $requestedRoles = is_array($requestedRoles) ? $requestedRoles : [$requestedRoles];

            $query->whereHas('roles', function ($q) use ($requestedRoles) {
                $q->whereIn('name', $requestedRoles);
            });

        }

        // 🔹 Filter by gender (Male or Female)
        if ($request->has('gender')) {
            $gender = $request->query('gender');
            if (in_array($gender, ['Male', 'Female'])) {
                $query->where('gender', $gender);
            }
        }

        if ($request->has('search')) {
            $searchTerm = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('phone', 'like', $searchTerm);
            });
        }

        $startDate = $request->query('startDate');
        $endDate   = $request->query('endDate');

        // Filter by created_at date range
        if (isset($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (isset($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Filter by region, related locations, and newly added fields
        foreach (['regional_office_id', 'cso_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        // Apply pagination only if requested
        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            $data    = $query->latest()->paginate($perPage);
            return response()->json(['data' => $data]);
        }

        $data = $query->latest()->get();

        return response()->json(['data' => $data]);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::with([
            'createdBy', 'updatedBy',
        ])->findOrFail($id);

        return response()->json($user);
    }

    /**
     * Store a newly created user.
     */
    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // Convert string "true"/"false" to actual boolean values
            $request->merge([
                'agree'               => filter_var($request->input('agree'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                'allow_notifications' => filter_var($request->input('allow_notifications'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            // Validate request (Removed 'role' validation)
            $validated = $request->validate([
                'name'                   => 'required|string|max:255',
                'address'                => 'required|string|max:255',
                'email'                  => 'required|email|unique:users,email',
                'password'               => 'required|string|min:8',
                // 'phone'                  => 'required|string|regex:/^\+\d{12}$/|unique:users,phone,',
                'phone'                  => 'required|string|regex:/^\d{12}$/|unique:users,phone,',
                'role'                   => 'required|string',
                'status'                 => 'nullable|string',
                'date_of_birth'          => 'nullable|date',
                'gender'                 => 'nullable|string|in:Male,Female,Prefer not to say',
                'photo'                  => 'nullable|image|max:2048', // Max 2MB image
                'agree'                  => 'nullable|boolean',
                'device_token'           => 'nullable|string',
                'web_app_firebase_token' => 'nullable|string',
                'allow_notifications'    => 'nullable|boolean',
                'regional_office_id'     => 'nullable|exists:regional_offices,id',
                'cso_id'                 => 'nullable|exists:csos,id',
            ]);

            $role = $validated['role'];
            unset($validated['role']);

            $validated['password']   = Hash::make($validated['password']);
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $validated['photo_url'] = $this->uploadPhoto($request->file('photo'), 'user_photos');
            }

            // Create user
            $user = User::create($validated);

            // Assign user to a role using Spatie
            if ($role) {
                $spatieRole = Role::where('name', $role)->first();
                if (isset($spatieRole)) {
                    $user->assignRole($spatieRole);
                }
            }

            // After DB::commit(); but before the final return

            if ($user->status === 'active') {
                $plainPassword = $request->input('password'); // Get the raw password

                // Send Email Notification
                if (! empty($user->email)) {
                    Mail::send('emails.user.user-account-created', [
                        'user'          => $user,
                        'plainPassword' => $plainPassword,
                    ], function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject("Your Account Has Been Created");
                    });
                }

            }

            // Log activity
            $this->logActivity(
                'user_created',
                "New user '{$user->name}' was created and assigned to role '{$role}'.",
                ['user_id' => $user->id, 'role' => $role, 'name' => $user->name]
            );

            DB::commit();

            return response()->json(['message' => 'User created successfully', 'data' => $user], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log error
            $this->logActivity(
                'user_create_failed',
                "Failed to create user. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while creating the user.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update the specified user.
     */
    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        // Find the user or fail
        $user = User::findOrFail($id);

        DB::beginTransaction();

        try {
            // Convert string "true"/"false" to actual boolean values
            $request->merge([
                'agree'               => filter_var($request->input('agree'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                'allow_notifications' => filter_var($request->input('allow_notifications'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            // Validation rules with unique email exception for current user
            $validated = $request->validate([
                'name'                   => 'sometimes|string|max:255',
                'address'                => 'sometimes|string|max:255',
                'email'                  => 'sometimes|email|unique:users,email,' . $user->id,
                'password'               => 'sometimes|string|min:8',
                // 'phone'                  => 'required|string|regex:/^\+\d{12}$/|unique:users,phone,' . $user->id,
                'phone'                  => 'required|string|regex:/^\d{12}$/|unique:users,phone,' . $user->id,
                'role'                   => 'sometimes|string|exists:roles,name', // Validate role exists
                'status'                 => 'sometimes|nullable|string',
                'date_of_birth'          => 'sometimes|nullable|date',
                'gender'                 => 'sometimes|nullable|string|in:Male,Female,Prefer not to say',
                'photo'                  => 'sometimes|nullable|image|max:2048', // Max 2MB image
                'agree'                  => 'sometimes|nullable|boolean',
                'device_token'           => 'sometimes|nullable|string',
                'web_app_firebase_token' => 'sometimes|nullable|string',
                'allow_notifications'    => 'sometimes|nullable|boolean',
                'regional_office_id'     => 'sometimes|nullable|exists:regional_offices,id',
                'cso_id'                 => 'sometimes|nullable|exists:csos,id',
            ]);

            // Prepare data for update
            $updateData = [];

            // Handle fields that can be updated
            $updateFields = [
                'name', 'email', 'phone', 'status', 'date_of_birth',
                'gender', 'agree', 'device_token',
                'web_app_firebase_token', 'allow_notifications',
                'regional_office_id', 'cso_id',
            ];

            foreach ($updateFields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            // Handle password update separately
            if (isset($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            // Always update the 'updated_by' field
            $updateData['updated_by'] = Auth::id();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete existing photo if exists
                if ($user->photo_url) {
                    $this->deleteExistingPhoto($user->photo_url);
                }

                // Upload new photo
                $updateData['photo_url'] = $this->uploadPhoto($request->file('photo'), 'user_photos');
            }

            // Handle role update
            $newRole = $validated['role'] ?? null;

            // Update user data
            $user->update($updateData);

            // Manage user roles
            if ($newRole) {
                // Remove existing roles
                $user->roles()->detach();

                // Find and assign new role
                $spatieRole = Role::where('name', $newRole)->first();
                if ($spatieRole) {
                    $user->assignRole($spatieRole);
                }
            }

            // Log update activity
            $this->logActivity(
                'user_updated',
                "User '{$user->name}' was updated.",
                [
                    'user_id'        => $user->id,
                    'updated_fields' => array_keys($updateData),
                    'role_changed'   => $newRole ? 'Yes' : 'No',
                ]
            );

            // Commit transaction
            DB::commit();

            // Refresh user to get latest data
            $user->refresh();

            return response()->json([
                'message' => 'User updated successfully',
                'data'    => $user,
            ]);

        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            // Log error
            $this->logActivity(
                'user_update_failed',
                "Failed to update user. Error: {$e->getMessage()}",
                [
                    'user_id'       => $user->id,
                    'error_line'    => $e->getLine(),
                    'error_message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while updating the user.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserProfile(Request $request)
    {
        $user = Auth::user();

        $user = User::where('id', Auth::user()->id)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validatedData = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'required|email|max:255|unique:users,email,' . $user->id,
            'password'            => 'required|string|min:8',
            // 'phone'               => 'required|string|regex:/^\+\d{12}$/|unique:users,phone,' . $user->id,
            'phone'               => 'required|string|regex:/^\d{12}$/|unique:users,phone,' . $user->id,
            'gender'              => 'required|in:Male,Female,Prefer not to say',
            'allow_notifications' => 'required|boolean',
        ]);

        DB::beginTransaction();

        try {
            $updateData = array_filter([
                'name'                => $validatedData['name'] ?? null,
                'email'               => $validatedData['email'] ?? null,
                'phone'               => $validatedData['phone'] ?? null,
                'gender'              => $validatedData['gender'] ?? null,
                'allow_notifications' => $validatedData['allow_notifications'] ?? null,
                'updated_by'          => $user->id,
                'password'            => isset($validatedData['password']) ? Hash::make($validatedData['password']) : null,
            ]);

            $user->update($updateData);

            if ($user->status === 'active') {

                // Send Email Notification
                if (! empty($user->email)) {
                    // Send email notification
                    Mail::send('emails.user.profile_updated', [
                        'user' => $user,
                    ], function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('Your profile has been updated');
                    });
                }

            }

            // Log activity
            $this->logActivity(
                'user profile update',
                "User '{$user->name}' updated their profile.",
                [
                    'user_id' => $user->id,
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'phone'   => $user->phone,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
                'data'    => $user,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log error
            $this->logActivity(
                'User Profile Update failed',
                "Failed to update user profile. Error: {$e->getMessage()}",
                [
                    'user_id'       => $user->id,
                    'error_line'    => $e->getLine(),
                    'error_message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while updating the profile',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Delete user photo if exists
        if ($user->photo_url) {
            $photoPath = ltrim(parse_url($user->photo_url, PHP_URL_PATH), '/');
            if (file_exists(public_path($photoPath))) {
                unlink(public_path($photoPath));
            }
        }

        $user->delete();

        // Log delete activity
        $this->logActivity(
            'user_deleted',
            "User '{$user->name}' was deleted.",
            ['user_id' => $user->id, 'name' => $user->name]
        );

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Bulk delete users and log a single entry.
     */
    public function bulkDestroy(Request $request)
    {
        $usersData = $request->input('itemsToDelete'); // Expecting an array of objects

        if (! is_array($usersData) || empty($usersData)) {
            return response()->json(['message' => 'Invalid or empty users data'], 400);
        }

        // Extract user IDs and emails from objects
        $userIds    = collect($usersData)->pluck('id')->toArray();
        $userEmails = collect($usersData)->pluck('email')->toArray();

        if (empty($userIds)) {
            return response()->json(['message' => 'No valid user IDs found'], 400);
        }

        $users = User::whereIn('id', $userIds)->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'No matching users found'], 404);
        }

        // Delete photos before deleting users
        foreach ($users as $user) {
            if (isset($user->photo_url)) {
                $photoPath = ltrim(parse_url($user->photo_url, PHP_URL_PATH), '/');
                if (file_exists(public_path($photoPath))) {
                    unlink(public_path($photoPath));
                }
            }
        }

        // Perform bulk delete
        User::whereIn('id', $userIds)->delete();

                                                          // Log a single entry for all deleted users
        $deletedBy     = Auth::user()->email ?? 'System'; // Get the authenticated user's email
        $deletedEmails = implode(', ', $userEmails);      // Convert emails array to string

        $this->logActivity(
            'bulk_user_deleted',
            "Users deleted: $deletedEmails by $deletedBy.",
            ['deleted_by' => $deletedBy, 'deleted_users' => $userEmails]
        );

        return response()->json(['message' => 'Users deleted successfully']);
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

    // Helper method to delete existing photo
    private function deleteExistingPhoto($photoUrl)
    {
        if ($photoUrl) {
            $photoPath = str_replace(url('/'), public_path(), $photoUrl);
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}
