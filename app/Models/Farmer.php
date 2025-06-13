<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Farmer extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $fillable = [
        'uuid',
        'name', 
    ];

    protected $hidden = [
        'id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($farmer) {
            if (empty($farmer->uuid)) {
                $farmer->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the route key for the model (use UUID instead of ID)
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function scopeByUuid($query, $uuid)
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * Get total number of farms
     */
    public function getTotalFarmsAttribute()
    {
        return $this->farms()->count();
    }

    /**
     * Get total farm size (sum of all farms)
     */
    public function getTotalFarmSizeAttribute()
    {
        return $this->farms()->sum('size');
    }

    public static function createNew($name)
    {
        return self::create(['name' => $name]);
    }
}
