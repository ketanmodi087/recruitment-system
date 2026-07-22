<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Agency extends Authenticatable
{
    protected $guard_name = 'web';
    use HasFactory, HasRoles, HasApiTokens, HasRoles, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'first_name',
        'last_name',
        'other_details',
        'country',
        'address',
        'phone',
        'profile',
        'password',
        'is_deleted',
        'is_disabled',
        'is_subscribed',
        'role_id',
        'created_by',
        'agency_id'
    ];

    // Define the relationship with the Recruiter model
    public function recruiters()
    {
        return $this->hasMany(Agency::class, 'agency_id');
    }

    // Define the relationship with the Payments model
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getUsersByRole(int $roleId)
    {
        return $this->where('role_id', $roleId)->get();
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'recruiter_id', 'id');
    }
}
