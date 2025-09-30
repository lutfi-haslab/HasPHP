<?php

namespace Hasphp\App\Models;

class User extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'users';
    
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'active',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the posts for the user.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
    
    /**
     * Get the user's profile.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }
    
    /**
     * Get the comments for the user.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
    
    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
    
    /**
     * Scope a query to only include users with verified email.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
    
    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
    
    /**
     * Hash the user's password.
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify the user's password.
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
