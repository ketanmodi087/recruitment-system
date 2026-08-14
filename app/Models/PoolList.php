<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoolList extends Model
{
    use HasFactory;
    protected $table = 'pool_list';
    protected $fillable = [
        'name',
        'created_by',
        'is_deleted'
    ];
}
