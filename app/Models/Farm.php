<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
    ];

    protected $casts = [
        'planting_date' => 'date',
        'current_season_month' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'full_location',
        'age_in_months',
        'expected_season_month'
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function getCurrentSeason()
    {
        return Season::where('month', $this->current_season_month)->first();
    }

    /**
     * Get full location string
     */
    public function getFullLocationAttribute()
    {
        return $this->village . ', ' . $this->district;
    }

    /**
     * Get farm age in months since planting
     */
    public function getAgeInMonthsAttribute()
    {
        return $this->planting_date->diffInMonths(Carbon::now());
    }

    public function getExpectedSeasonMonthAttribute()
    {
        $monthsElapsed = $this->age_in_months;
        return ($monthsElapsed % 12) + 1;
    }

    /**
     * Check if farm season needs updating
     */
    public function needsSeasonUpdate()
    {
        return $this->current_season_month !== $this->expected_season_month;
    }

    /**
     * Update farm to current expected season
     */
    public function updateToCurrentSeason()
    {
        if ($this->needsSeasonUpdate()) {
            $this->update(['current_season_month' => $this->expected_season_month]);
            return true;
        }
        return false;
    }

    /**
     * Scope: Farms that need season updates
     */
    public function scopeNeedingSeasonUpdate($query)
    {
        return $query->get()->filter(function ($farm) {
            return $farm->needsSeasonUpdate();
        });
    }

    public function scopePlantedInMonth($query, $month)
    {
        return $query->whereMonth('planting_date', $month);
    }

}
