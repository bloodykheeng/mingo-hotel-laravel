<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'email',
        'password',
        'status',
        'lastlogin',
        'photo_url',
        'agree',
        'phone',
        'age',
        'date_of_birth',
        'gender',
        'nationality',
        'device_token',
        'web_app_firebase_token',
        'role',
        'created_by',
        'updated_by',
        'allow_notifications',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function setEmailAttribute($value)
    {
        if (empty($value)) { // will check for empty string
            $this->attributes['email'] = null;
        } else {
            $this->attributes['email'] = $value;
        }
    }

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['role'];

    /**
     * Get the user's role dynamically.
     *
     * @return string
     */
    public function getRoleAttribute()
    {
        // Get the first role of the user
        // If you want to handle multiple roles differently, modify this logic
        // last will get the most latest assigned role
        return $this->roles->last()?->name ?? 'No Role';
    }

    public function scopeFilterByLatestRole($query, $roles)
    {
        return $query->whereHas('roles', function ($q) use ($roles) {
            $q->whereIn('name', (array) $roles);
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Feedback belongs to a user (who updated it)
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
