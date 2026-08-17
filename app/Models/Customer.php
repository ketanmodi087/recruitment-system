<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'contract_ref_no',
        'contract_brief',
        'is_deleted',
        'created_by',
        'project_lead_name',
        'project_lead_phone',
        'tasks',
        'notes'
    ];

    protected $casts = [
        'tasks' => 'array',
        'notes' => 'array'
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'created_by');
    }
}
