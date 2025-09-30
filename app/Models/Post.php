<?php

namespace Hasphp\App\Models;

class Post extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'posts';
    
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'title',
        'content',
        'excerpt',
        'slug',
        'user_id',
        'published',
        'featured',
        'views_count',
        'published_at',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'published' => 'boolean',
        'featured' => 'boolean',
        'views_count' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the user that owns the post.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * Get the comments for the post.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }
    
    /**
     * The tags that belong to the post.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }
    
    /**
     * Scope a query to only include featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
    
    /**
     * Increment the views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
    
    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
    
    /**
     * Scope a query to only include posts by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Get the post's excerpt.
     */
    public function getExcerptAttribute(): string
    {
        return substr(strip_tags($this->content), 0, 100) . '...';
    }
}
