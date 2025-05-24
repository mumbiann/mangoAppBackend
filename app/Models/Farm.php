<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farm extends Model
{
    protected $fillable = [
        'farmer_id', 
        'name', 
        'size', 
        'district', 
        'village',
        'planting_date', 
        'current_season_month', 
        'last_synced_at'
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
