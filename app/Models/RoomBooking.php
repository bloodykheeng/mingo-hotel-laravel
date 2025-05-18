<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'check_in',
        'check_out',
        'status', // new, accepted, rejected
        'number_of_adults',
        'number_of_children',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'check_in'           => 'datetime',
        'check_out'          => 'datetime',
        'number_of_adults'   => 'integer',
        'number_of_children' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
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
