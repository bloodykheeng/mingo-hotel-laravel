<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'feature_id',
        'photo_url',
        'amount',
        'created_by',
        'updated_by',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
