<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Note extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 
        'farm_id', 
        'farmer_id', 
        'title', 
        'content', 
        'is_deleted'
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
}
