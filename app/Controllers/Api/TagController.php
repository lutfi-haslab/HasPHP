<?php

namespace Hasphp\App\Controllers\Api;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;

class TagController
{
    /**
     * Display a listing of tags.
     */
    public function index($request, $response)
    {
        try {
            $db = $request->app->resolve('db');
            
            $tags = $db->table('tags')
                ->select([
                    'tags.*',
                    'COUNT(post_tags.post_id) as posts_count'
                ])
                ->leftJoin('post_tags', 'post_tags.tag_id', '=', 'tags.id')
                ->leftJoin('posts', function($join) {
                    $join->on('posts.id', '=', 'post_tags.post_id')
                         ->where('posts.published', '=', 1);
                })
                ->groupBy('tags.id')
                ->orderBy('posts_count', 'desc')
                ->get();
            
            $response->json([
                'success' => true,
                'data' => $tags
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch tags',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Display the specified tag with its posts.
     */
    public function show($request, $response, $slug)
    {
        try {
            $db = $request->app->resolve('db');
            
            $tag = $db->table('tags')
                ->where('slug', '=', $slug)
                ->first();
            
            if (!$tag) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'Tag not found'
                ]);
                return;
            }
            
            // Get posts with this tag
            $tag['posts'] = $db->table('posts')
                ->select([
                    'posts.*',
                    'users.name as author_name',
                    'users.avatar as author_avatar'
                ])
                ->join('post_tags', 'post_tags.post_id', '=', 'posts.id')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('post_tags.tag_id', '=', $tag['id'])
                ->where('posts.published', '=', 1)
                ->orderBy('posts.created_at', 'desc')
                ->get();
            
            $response->json([
                'success' => true,
                'data' => $tag
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch tag',
                'message' => $e->getMessage()
            ]);
        }
    }
}
