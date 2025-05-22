<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserPermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userPermissions = Permission::all();
        return response()->json($userPermissions, 200);
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

    public function permissionNotInCurrentRole($id)
    {
        $role = Role::findOrFail($id); // Fetch the role with the given ID

        // Get the permissions of the current role
        $currentRolePermissions = $role->permissions;

        // Get all available permissions
        $allPermissions = Permission::all();

        // Filter the available permissions to find the ones not present in the current role
        $permissionsNotInCurrentRole = $allPermissions->filter(function ($permission) use ($currentRolePermissions) {
            return ! $currentRolePermissions->contains('id', $permission->id);
        });

        return response()->json([ ...$permissionsNotInCurrentRole]);
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
