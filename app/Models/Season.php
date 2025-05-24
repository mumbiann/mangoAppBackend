<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = [
        'month', 
        'title', 
        'short_description', 
        'full_instructions', 
        'activities',
    ];

    protected $casts = [
        'activities' => 'array',
    ];
}
