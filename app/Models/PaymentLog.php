<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',       
        'platform',  
        'payload',     
    ];


    protected $casts = [
        'payload' => 'array',  
    ];
}