<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'customer_id',
        'invoice_number',
        'date',
        'payment_details',
        'other_comments',
        'is_deleted',
        'currency',
        'created_by'
    ];

    protected $casts = [
        'payment_details' => 'array',
        'other_comments' => 'array',
        'currency' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($invoice) {
            $invoice->invoice_number = 'INV-' . uniqid();
        });
    }
}
