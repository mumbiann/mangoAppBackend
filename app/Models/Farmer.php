<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Farmer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 
        'phone', 
        'email', 
        'password', 
        'district', 
        'village'
    ];

    protected $hidden = [
        'password'
    ];

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
