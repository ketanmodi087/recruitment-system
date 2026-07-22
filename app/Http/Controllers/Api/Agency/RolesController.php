<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolesRequest;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function addEditRole(RolesRequest $request)
    {
        if ($this->user->hasPermissionTo('roles_add')) {
            if ($request->get('role_id')) {
                $role = Role::find($request->get('role_id'));
                $role->update([
                    'name' => $request->get('name'),
                ]);
            } else {
                $role = Role::create([
                    'name' => $request->get('name'),
                    'created_by' => Auth::id(),
                    'guard_name' => 'web',
                ]);
            }

            if ($role) {
                $permissions = $request->get('permissions', []);
                // $masterpermission = ['country_list', 'country_add', 'country_update', 'country_delete', 'category_list', 'category_add', 'category_update', 'category_delete', 'subcategory_list', 'subcategory_add', 'subcategory_update', 'subcategory_delete', 'candidatepool_list', 'candidatepool_add', 'candidatepool_update', 'candidatepool_delete', 'master'];
                // $paymentHistory = ['payment_history', 'paymentHistory'];

                $customPermissions = [
                    'master' => [
                        'master',
                        'country_list',
                        'country_add',
                        'country_update',
                        'country_delete',
                        'category_list',
                        'category_add',
                        'category_update',
                        'category_delete',
                        'subcategory_list',
                        'subcategory_add',
                        'subcategory_update',
                        'subcategory_delete',
                        'candidatepool_list',
                        'candidatepool_add',
                        'candidatepool_update',
                        'candidatepool_delete'
                    ],
                    'paymentHistory' => [
                        'payment_history',
                        'paymentHistory',
                    ]
                ];

                // Prepare a list of permissions to assign
                $data = [];
                foreach ($permissions as $permissionName) {
                    $permission = Permission::where('name', 'like', '%' . $permissionName . '%')->get();
                    foreach ($permission as $perms) {
                        if (isset($customPermissions[$perms->name])) {
                            $data = array_merge($data, $customPermissions[$perms->name]);
                        } else {
                            $data[] = $perms->name;
                        }
                    }
                }
                // Synchronize permissions with the role
                $role->syncPermissions($data);

                return response()->json([
                    'message' => 'New Role Created successfully with permissions.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => 'Sorry!! Role not created.',
                    'status' => 422
                ], 422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function roleList(Request $request)
    {
        if ($this->user->hasPermissionTo('roles_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $rolesQuery = Role::whereNotIn('id', [1, 2])
                ->where('is_deleted', 0)
                ->where(function ($query) {
                    $query->where('created_by', Auth::id())
                        ->orWhere('created_by', 0);
                })
                ->with('permissions');

            if ($page || $perPage) {
                if (!empty($search)) {
                    $rolesQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%");
                    });
                }

                $rolesData = $rolesQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                $encryptedJobs = $rolesData->map(function ($role) {
                    $role['permissions'] = $role->permissions->pluck('name');
                    return $role;
                });

                $roles = new LengthAwarePaginator(
                    $encryptedJobs,
                    $rolesData->total(),
                    $rolesData->perPage(),
                    $rolesData->currentPage(),
                    ['path' => LengthAwarePaginator::resolveCurrentPath()]
                );
            } else {
                $roles = $rolesQuery->orderBy('created_at', 'desc')->get();
            }



            if ($roles->isNotEmpty()) {
                return response()->json([
                    'message' => 'Roles list fetched successfully.',
                    'roles' => $roles,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => 'No roles found.',
                    'roles' => $roles,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }


    public function roleDelete($id)
    {
        if ($this->user->hasPermissionTo('roles_delete')) {
            $agencies = Agency::where('role_id', $id)
                ->get()->toArray();
            if ($agencies) {
                return response()->json([
                    'error' => "Sorry!! You can't delete this Role, It's already assigned to another agencies.",
                    'status' => 403
                ], 403);
            } else {
                $roles = Role::find($id);
                $roles->update(['is_deleted' => 1]);
                if ($roles) {
                    return response()->json([
                        'message' => 'Role deleted successfully.',
                        'status' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'error' => 'No roles found.',
                        'roles' => $roles,
                        'status' => 200
                    ]);
                }
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function roleListSuperAdmin($id)
    {
        if ($this->user->hasPermissionTo('roles_list')) {
            $roles = Role::where(function ($query) use ($id) {
                $query->where('created_by', $id)
                    ->orWhere('created_by', 0);
            })
                ->where('is_deleted', 0)
                ->with('permissions') // Eager load permissions
                ->orderBy('created_at', 'DESC')
                ->get();

            if ($roles->isNotEmpty()) {
                $rolesWithPermissions = $roles->map(function ($role) {
                    return [
                        'role' => $role,
                        'permissions' => $role->permissions->pluck('name'),
                    ];
                });

                return response()->json([
                    'message' => 'Roles list fetched successfully.',
                    'roles' => $rolesWithPermissions,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => 'No roles found.',
                    'roles' => $roles,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }
}
