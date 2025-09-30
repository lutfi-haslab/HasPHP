<?php

namespace Hasphp\App\Models;

class Comment extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'comments';
    
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'post_id',
        'user_id',
        'content',
        'approved',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'approved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the post that owns the comment.
     */
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }
    
    /**
     * Get the user that owns the comment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * Scope a query to only include approved comments.
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }
}
