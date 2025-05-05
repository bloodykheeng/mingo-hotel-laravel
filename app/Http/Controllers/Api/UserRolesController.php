<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Stevebauman\Location\Facades\Location;

class UserRolesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userRoles = Role::with("permissions")->get();
        return response()->json($userRoles, 200);
    }

    public function getAssignableRoles(Request $request)
    {
        $user     = Auth::user(); // Get the authenticated user
        $allRoles = Role::all()->pluck('name');

        // Define role mapping
        $roleMappings = [
            'System Admin' => $allRoles->toArray(), // Convert to array
            'PPDA Admin'   => ['PPDA Officer', 'CSO Admin'],
            'CSO Admin'    => ['CSO Monitor', 'CSO Verifier', 'CSO Approver'],
        ];

                                                         // Get the user's role
        $userRole = $user->roles->pluck('name')->last(); // Assuming single role per user

        // Determine assignable roles based on user role
        $assignableRoles = $roleMappings[$userRole] ?? [];

        // If 'roles' is provided in the request, filter based on it
        if ($request->has('roles') && is_array($request->roles) && ! empty($request->roles)) {
            $assignableRoles = array_intersect($assignableRoles, $request->roles);
        }

        // Apply user category filter
        $userCategory = $request->query('usersCategory');

        if (isset($userCategory)) {
            if ($userCategory === 'cso_users') {
                $categoryRoles = ["CSO Admin", "CSO Monitor", "CSO Verifier", "CSO Approver"];
            } elseif ($userCategory === 'ppda_users') {
                $categoryRoles = ["PPDA Admin", "PPDA Officer", "System Admin"];
            } else {
                return response()->json(['message' => 'Invalid Users Category'], 400);
            }

            // Filter assignable roles based on user category
            $assignableRoles = array_intersect($assignableRoles, $categoryRoles);
        }

        return response()->json(array_values((array) $assignableRoles), 200);
    }

    /**
     * Get roles with permissions.
     *
     * @return \Illuminate\Http\Response
     */
    public function getRolesWithModifiedPermissions()
    {
        $roles       = Role::with('permissions')->get();
        $permissions = Permission::all()->pluck('name')->toArray();

        $result = $roles->map(function ($role) use ($permissions) {
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            $formattedPermissions = collect($permissions)->map(function ($permission) use ($rolePermissions) {
                return [
                    'name'  => $permission,
                    'value' => in_array($permission, $rolePermissions),
                ];
            });

            return [
                'role'        => $role->name,
                'permissions' => $formattedPermissions,
            ];
        });

        return response()->json($result, 200);
    }

    /**
     * Sync permissions to a role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function syncPermissionsToRole(Request $request)
    {

        // $requestData = $request->all();

        // return response()->json(['message' => 'Unauthorized', 'permoissionData' => $requestData['roles']], 403);

        // Validate the incoming request data
        $validatedData = $request->validate([
            'roles'                       => 'required|array',
            'roles.*.role'                => 'required|string|exists:roles,name',
            'roles.*.permissions'         => 'required|array',
            'roles.*.permissions.*.name'  => 'required|string|exists:permissions,name',
            'roles.*.permissions.*.value' => 'required',
        ]);
        foreach ($validatedData['roles'] as $roleData) {
            $role = Role::where('name', $roleData['role'])->firstOrFail();

            // Convert string booleans to actual boolean values
            $permissions = collect($roleData['permissions'])->map(function ($permission) {
                $permission['value'] = filter_var($permission['value'], FILTER_VALIDATE_BOOLEAN);
                return $permission;
            });

            // Filter and pluck permission names where value is true
            $permissionNames = $permissions->filter(function ($permission) {
                return $permission['value'] == true;
            })->pluck('name')->toArray();

            $permissions = Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissions);
        }

        // Log the permission sync activity with roles and their permissions
        $logDetails = array_map(function ($roleData) {
            $permissions = collect($roleData['permissions'])->map(function ($permission) {
                // Ensure the value is boolean
                $permission['value'] = filter_var($permission['value'], FILTER_VALIDATE_BOOLEAN);
                return $permission;
            });

            // Include all permissions with their values
            $permissionsWithValues = $permissions->map(function ($permission) {
                return "{$permission['name']}: " . ($permission['value'] ? 'true' : 'false');
            })->toArray();

            return [
                'role'        => $roleData['role'],
                'permissions' => $permissionsWithValues,
            ];
        }, $validatedData['roles']);

        // Construct the log string to show both true and false values
        $roleNames = implode(', ', array_map(function ($roleDetail) {
            $permissions = implode(', ', $roleDetail['permissions']);
            return "{$roleDetail['role']} ({$permissions})";
        }, $logDetails));

        $ip = $request->ip();           // Get IP address
                                        // $ip = '103.239.147.187';
        $location = Location::get($ip); // Get location details

        $agent     = new Agent();
        $userAgent = $request->header('User-Agent'); // Get User-Agent header

        activity('permissions sync')
            ->causedBy(Auth::user()) // Log the user performing the sync
            ->withProperties([
                'synced_roles'     => $logDetails, // Include details of roles and their permissions
                'created_by'       => Auth::user()->name ?? null,
                'created_by_email' => Auth::user()->email ?? null,
                'ip'               => $ip ?? null,
                'country'          => $location->countryName ?? null,
                'city'             => $location->cityName ?? null,
                'region'           => $location->regionName ?? null,
                'latitude'         => $location->latitude ?? null,
                'longitude'        => $location->longitude ?? null,
                'timezone'         => $location->timezone ?? null,
                'user_agent'       => $userAgent,
                'device'           => $agent->device(),
                'platform'         => $agent->platform(),
                'platform_version' => $agent->version($agent->platform()),
                'browser'          => $agent->browser(),
                'browser_version'  => $agent->version($agent->browser()),
            ])
            ->log("Permission sync performed by " . Auth::user()->name . ". Synced roles: {$roleNames}");

        return response()->json(['message' => 'Permissions synced to roles successfully']);
    }

    public function addPermissionsToRole(Request $request)
    {
        // $requestData = $request->all();

        $roleID        = $request->role_id;
        $permissionIDs = $request->permission_ids;

        // $role = Role::findById($roleID); // Find the role by ID
        $role        = Role::find($roleID);
        $permissions = Permission::whereIn('id', $permissionIDs)->get(); // Get the permissions based on the IDs

        // $role->syncPermissions($permissions); // Sync the permissions to the role
        $role->permissions()->attach($permissions);

        return response()->json(['message' => 'Permissions added to role successfully']);
    }

    // public function getAssignableRoles(Request $request)
    // {
    //     if ($request->user()->hasRole('OAG Admin')) {
    //         $roles = Role::all()->pluck('name');
    //     } else {
    //         $roles = Role::whereNotIn('name', ['OAG Admin'])->pluck('name');
    //     }
    //     return response()->json($roles, 200);
    // }

    public function deletePermissionFromRole(Request $request)
    {
        $roleID       = $request->role_id;
        $permissionID = $request->permission_id;

        $role       = Role::findOrFail($roleID);             // Find the role by ID
        $permission = Permission::findOrFail($permissionID); // Find the permission by ID

        $role->revokePermissionTo($permission); // Revoke the permission from the role

        return response()->json(['message' => 'Permission deleted from role successfully']);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}