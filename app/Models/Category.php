<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $table = "category";
    protected $fillable = [
        'name',
        'is_deleted',
        'created_by'
    ];

    public function subcategory()
    {
        return $this->hasMany(SubCategory::class, 'category_id');
    }
}
