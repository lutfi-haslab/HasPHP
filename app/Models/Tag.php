<?php

namespace Hasphp\App\Models;

class Tag extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'tags';
    
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'name',
        'slug',
        'color',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'created_at' => 'datetime',
    ];
    
    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = false;
    
    /**
     * The posts that belong to the tag.
     */
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tags', 'tag_id', 'post_id');
    }
    
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
