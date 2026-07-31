<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountryRequest;
use App\Http\Requests\CategoryRequest;
use App\Http\Requests\PoolRequest;
use App\Http\Requests\SubCategoryRequest;
use App\Models\Country;
use App\Models\Category;
use App\Models\Job;
use App\Models\PoolList;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MastersController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    // ==============================|| Country functions ||============================== //
    public function countryList(Request $request, $type = null)
    {
        if ($this->user->hasPermissionTo('country_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type1 = $request->input('type') ? $request->input('type') : 'desc';

            if ($type === 'recruiter') {
                $countriesQuery = Country::where('is_deleted', 0)->where('created_by', Auth::id());
                // $countriesQuery = Country::where('is_deleted', 0)->where('created_by', Auth::user()->created_by);
            } else {
                $countriesQuery = Country::where('is_deleted', 0)->where('created_by', Auth::id());
            }

            if ($page || $perPage) {
                if (!empty($search)) {
                    $countriesQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    });
                }
                $countries = $countriesQuery
                    ->orderBy($columnName, $type1)
                    ->paginate($perPage, ['*'], 'page', $page);
            } else {
                $countries = $countriesQuery->orderBy('created_at', 'desc')->get();
            }

            if ($countries->isNotEmpty()) {
                return response()->json([
                    'message' => 'All countries get successfully.',
                    'countries' => $countries,
                    'status' => 200
                ]);
            } else {
                return response()->json([
                    'message' => "No countries found.",
                    'countries' => $countries,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addCountry(CountryRequest $request)
    {
        if ($this->user->hasPermissionTo('country_add')) {
            $country = Country::create([
                'name' => $request->get('name'),
                'code' => $request->get('code'),
                'created_by' => Auth::id()
            ]);
            if (!empty($country)) {
                return response()->json([
                    'message' => 'New country created successfully.',
                    'status' => 200
                ]);
            } else {
                return response()->json([
                    'error' => "Sorry!! Country not Created.",
                    'status' => 422
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function updateCountry(CountryRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('country_update')) {
            $country = Country::find($id);
            $country->update($request->all());
            if ($country) {
                return response()->json([
                    'message' => 'Country updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Country not updated.",
                    'status' => 422
                ], 422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deleteCountry(Request $request)
    {
        if ($this->user->hasPermissionTo('country_delete')) {
            $country_ids = $request->get('country_ids');
            if (!empty($country_ids)) {
                foreach ($country_ids as $country_id) {
                    $country = Country::find($country_id);
                    $jobs = Job::where('country_id', $country_id)->where('is_deleted', 0)->count();
                    if ($jobs > 0) {
                        return response()->json(['error' => 'Cannot delete this Country. Applications Or Job already using this country'], 403);
                    } else {
                        $country->update(['is_deleted' => 1]);
                    }
                }
                return response()->json([
                    'message' => 'Countries Deleted successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Countries.",
                    'status' => 422
                ], 422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    // ==============================|| Category functions ||============================== //
    public function categoryList(Request $request, $type = null)
    {
        if ($this->user->hasPermissionTo('category_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type1 = $request->input('type') ? $request->input('type') : 'desc';

            if ($type === 'recruiter') {
                $categoryQuery = Category::where('is_deleted', 0)->where('created_by', Auth::id());
                // $user = Auth::user()->created_by;
                // $categoryQuery = Category::where('is_deleted', 0)->where('created_by', $user);
            } else {
                $categoryQuery = Category::where('is_deleted', 0)->where('created_by', Auth::id());
            }


            if ($page || $perPage) {
                if (!empty($search)) {
                    $categoryQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%");
                    });
                }

                $category = $categoryQuery
                    ->orderBy($columnName, $type1)
                    ->paginate($perPage, ['*'], 'page', $page);
            } else {
                $category = $categoryQuery->orderBy('created_at', 'desc')->get();
            }

            if ($category->isNotEmpty()) {
                return response()->json([
                    'message' => 'All categories get successfully.',
                    'categories' => $category,
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "No categories found.",
                    'categories' => $category,
                    'status' => 200
                ],  200);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addCategory(CategoryRequest $request)
    {
        if ($this->user->hasPermissionTo('category_add')) {
            $category = Category::create([
                'name' => $request->get('name'),
                'created_by' => Auth::id()
            ]);
            if (!empty($category)) {
                return response()->json([
                    'message' => 'New category created successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Category not Created.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function updateCategory(CategoryRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('category_update')) {
            $category = Category::find($id);
            $category->update($request->all());
            if ($category) {
                return response()->json([
                    'message' => 'Category updated successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Category not updated.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deleteCategory(Request $request)
    {
        if ($this->user->hasPermissionTo('category_delete')) {
            $category_ids = $request->get('category_ids');
            if (!empty($category_ids)) {
                foreach ($category_ids as $category_id) {
                    $category = Category::find($category_id);
                    $jobs = Job::where('category_id', $category_id)->where('is_deleted', 0)->count();
                    $subcategory = SubCategory::where('category_id', $category_id)->where('is_deleted', 0)->count();

                    if ($jobs > 0 || $subcategory > 0) {
                        return response()->json(['error' => 'Cannot delete this Category. Applications , Job or Sub Category already using this Category'], 403);
                    } else {
                        $category->update(['is_deleted' => 1]);
                    }
                }
                return response()->json([
                    'message' => 'Category Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Category.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    // ==============================|| Sub Category functions ||============================== //

    public function subCategoryList(Request $request, $type = null)
    {
        if ($this->user->hasPermissionTo('subcategory_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type1 = $request->input('type') ? $request->input('type') : 'desc';

            if ($type === 'recruiter') {
                $subcategoryQuery = SubCategory::with('category')->where('created_by', Auth::id())->where('is_deleted', 0);
                // $user = Auth::user()->created_by;
                // $subcategoryQuery = SubCategory::with('category')->where('created_by', $user)->where('is_deleted', 0);
            } else {
                $subcategoryQuery = SubCategory::with('category')->where('created_by', Auth::id())->where('is_deleted', 0);
            }

            if ($page || $perPage) {
                if (!empty($search)) {
                    $subcategoryQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%")
                            ->orWhereHas('category', function ($query) use ($search) {
                                $query->where(function ($query) use ($search) {
                                    $query->where('name', 'like', '%' . $search . '%')
                                        ->orWhere('name', 'like', '%' . $search . '%');
                                });
                            });
                    });
                }
                $subcategory = $subcategoryQuery
                    ->orderBy($columnName, $type1)
                    ->paginate($perPage, ['*'], 'page', $page);
            } else {
                $subcategory = $subcategoryQuery->orderBy('created_at', 'desc')->get();
            }

            if ($subcategory->isNotEmpty()) {
                return response()->json([
                    'message' => 'All subcategories get successfully.',
                    'subcategories' => $subcategory,
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "No subcategory found.",
                    'subcategories' => $subcategory,
                    'status' => 200
                ],  200);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addSubCategory(SubCategoryRequest $request)
    {
        if ($this->user->hasPermissionTo('subcategory_add')) {
            $subcategory = SubCategory::create([
                'name' => $request->get('name'),
                'category_id' => $request->get('category_id'),
                'created_by' => Auth::id()
            ]);
            if (!empty($subcategory)) {
                return response()->json([
                    'message' => 'New subcategory created successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Sub category not Created.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function updateSubCategory(SubCategoryRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('subcategory_update')) {
            $subcategory = SubCategory::find($id);
            $subcategory->update($request->all());
            if ($subcategory) {
                return response()->json([
                    'message' => 'Sub category updated successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Sub Category not updated.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deleteSubCategory(Request $request)
    {
        if ($this->user->hasPermissionTo('subcategory_delete')) {
            $subcategory_ids = $request->get('subcategory_ids');
            if (!empty($subcategory_ids)) {
                foreach ($subcategory_ids as $subcategory_id) {
                    $subcategory = SubCategory::find($subcategory_id);
                    $jobs = Job::where('subcategory_id', $subcategory_id)->where('is_deleted', 0)->count();
                    if ($jobs > 0) {
                        return response()->json(['error' => 'Cannot delete this Sub Category. Applications Or Job already using this Sub Category'], 403);
                    } else {
                        $subcategory->update(['is_deleted' => 1]);
                    }
                }
                return response()->json([
                    'message' => 'Sub Category Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete sub category.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function poolList(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $poolQuery = PoolList::where('created_by', Auth::id())
                ->where('is_deleted', 0);
            if ($page || $perPage) {
                if (!empty($search)) {
                    $poolQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%");
                    });
                }
                $pool = $poolQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
            } else {
                $pool = $poolQuery->orderBy('created_at', 'desc')->get();
            }
            if ($pool->isNotEmpty()) {
                return response()->json([
                    'message' => 'All pool get successfully.',
                    'pool' => $pool,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No pool list found.",
                    'pool' => $pool,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addPool(PoolRequest $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_add')) {
            $pool = PoolList::create([
                'name' => $request->get('name'),
                'created_by' => Auth::id()
            ]);
            if (!empty($pool)) {
                return response()->json([
                    'message' => 'New pool created successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Pool not Created.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function updatePool(PoolRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('candidatepool_update')) {
            $pool = PoolList::find($id);
            $pool->update($request->all());
            if ($pool) {
                return response()->json([
                    'message' => 'Pool updated successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Pool not updated.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deletePool(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_delete')) {
            $pool_ids = $request->get('pool_ids');
            if (!empty($pool_ids)) {
                foreach ($pool_ids as $pool_id) {
                    $pool = PoolList::find($pool_id);
                    $pool->update(['is_deleted' => 1]);
                }
                return response()->json([
                    'message' => 'Pool Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Pool.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }
}
