<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Job extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'country_id',
        'last_date_apply',
        'category_id',
        'subcategory_id',
        'recruiter_id',
        'tags_points',
        'is_deleted',
        'created_by',
        'images',
        'city'
    ];

    protected $casts = [
        'tags_points' => 'array',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id');
    }
    public function recruiter()
    {
        return $this->belongsTo(Agency::class, 'recruiter_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(SubCategory::class);
    }
}
