<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'agency_id',
        'start_date',
        'type',
        'expiry_date',
        'subscription_id',
        'amount',
        'other_details',
        'status',
        'transaction_id'
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
