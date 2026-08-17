<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;
    protected $table = "sub_category";
    protected $fillable = [
        'name',
        'category_id',
        'is_deleted',
        'created_by'
    ];
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
