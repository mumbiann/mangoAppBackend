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
        'month' => 'integer',
        'activities' => 'array',
    ];

    public static function getSummary()
    {
        return self::select('month', 'title', 'short_description')
                   ->orderBy('month')
                   ->get();
    }

    /**
     * Get full season details by month
     */
    public static function getFullDetails($month)
    {
        return self::where('month', $month)->first();
    }

    /**
     * Get all seasons with full details
     */
    public static function getAllDetails()
    {
        return self::orderBy('month')->get();
    }

    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Get next season
     */
    public function getNextSeason()
    {
        $nextMonth = $this->month >= 12 ? 1 : $this->month + 1;
        return self::where('month', $nextMonth)->first();
    }

    public function getPreviousSeason()
    {
        $prevMonth = $this->month <= 1 ? 12 : $this->month - 1;
        return self::where('month', $prevMonth)->first();
    }

    /**
     * Format activities for API response
     */
    public function getFormattedActivitiesAttribute()
    {
        return $this->activities ?? [];
    }

    /**
     * Check if season has activities
     */
    public function hasActivities()
    {
        return !empty($this->activities);
    }
}
