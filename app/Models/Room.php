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
        'photo_url',
        'room_type',
        'room_category_id',
        'status',
        'price',
        'stars',
        'booked',
        'number_of_adults',
        'number_of_children',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price'              => 'float',
        'stars'              => 'integer',
        'booked'             => 'boolean',
        'number_of_adults'   => 'integer',
        'number_of_children' => 'integer',
    ];

    public function roomCategory()
    {
        return $this->belongsTo(RoomCategory::class);
    }

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
