<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
    'status',
    'access_level', 
    'registration_ip', 
    'user_agent', 
    'terms_accepted_at', 
    'profile_image',
    'phone_number',
    'address',
    'city',
    'state',
];

    public function tasks() {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'password' => 'hashed',
        ];
    }

    // Add this relationship to your existing User model
public function pagePermissions()
{
    return $this->hasMany(UserPagePermission::class);
}

// Add this method to get permission value for a page
public function getPagePermission($pageKey)
{
    $permission = $this->pagePermissions()
        ->whereHas('page', function($query) use ($pageKey) {
            $query->where('page_key', $pageKey);
        })
        ->first();
    
    return $permission ? $permission->permission_value : 0;
}
}
