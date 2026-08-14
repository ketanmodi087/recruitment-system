<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'template_id',
        'created_by',
        'title',
        'logo',
        'description',
        'addition_texts',
        'buttons',
        'images',
        'html'
    ];
    protected $casts = [
        'addition_texts' => 'array',
        'buttons' => 'array',
        'images' => 'array'
    ];
}
