<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'paypal_order_id',
        'paypal_capture_id',
        'payer_email',
        'amount',
        'currency',
        'status',
        'response_data',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'response_data' => 'array',
        ];
    }
}