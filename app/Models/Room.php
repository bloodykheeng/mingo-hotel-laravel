<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stars',
        'booked',
        'number_of_adults',
        'number_of_children',
        'created_by',
        'updated_by',
    ];

    public function roomFeatures()
    {
        return $this->hasMany(RoomFeature::class);
    }

    public function roomAttachments()
    {
        return $this->hasMany(RoomAttachment::class);
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
