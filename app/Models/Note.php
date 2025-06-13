<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
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

    public function scopeForFarm($query, $farmId)
    {
        return $query->where('farm_id', $farmId);
    }

    /**
     * Scope: Notes created in the last N days
     */
    public function scopeRecentDays($query, $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Get note excerpt (first 50 characters)
     */
    public function getExcerptAttribute()
    {
        return Str::limit($this->content, 50);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%");
        });
    }
}
