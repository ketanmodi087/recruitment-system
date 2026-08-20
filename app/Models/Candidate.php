<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;
    protected $fillable = [
        'created_by',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cv',
        'experience',
        'address',
        'is_deleted',
        'pool_list_id',
        'match_payload',
        'country_id',
        'category_id',
        'subcategory_id',
        'status',
        'notes',
        'tasks'
    ];

    protected $casts = [
        'match_payload' => 'array',
        'notes' => 'array',
        'tasks' => 'array'
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
