<?php

namespace Hasphp\App\Controllers\Api;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;

class UserController
{
    /**
     * Display a listing of users.
     */
    public function index($request, $response)
    {
        try {
            $db = $request->app->resolve('db');
            
            $limit = min((int)($request->query['limit'] ?? 20), 100);
            $offset = (int)($request->query['offset'] ?? 0);
            
            $users = $db->table('users')
                ->select([
                    'id', 'name', 'email', 'avatar', 'bio', 'active', 'created_at'
                ])
                ->where('active', '=', 1)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();
            
            // Get post counts for each user
            foreach ($users as &$user) {
                $postCount = $db->table('posts')
                    ->where('user_id', '=', $user['id'])
                    ->where('published', '=', 1)
                    ->count();
                $user['posts_count'] = $postCount;
            }
            
            $response->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch users',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Display the specified user.
     */
    public function show($request, $response, $id)
    {
        try {
            $db = $request->app->resolve('db');
            
            $user = $db->table('users')
                ->select([
                    'id', 'name', 'email', 'avatar', 'bio', 'active', 'created_at'
                ])
                ->where('id', '=', $id)
                ->where('active', '=', 1)
                ->first();
            
            if (!$user) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'User not found'
                ]);
                return;
            }
            
            // Get user's published posts
            $user['posts'] = $db->table('posts')
                ->select([
                    'id', 'title', 'slug', 'excerpt', 'featured', 'views_count', 'published_at', 'created_at'
                ])
                ->where('user_id', '=', $user['id'])
                ->where('published', '=', 1)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get user's stats
            $user['stats'] = [
                'total_posts' => count($user['posts']),
                'total_views' => array_sum(array_column($user['posts'], 'views_count')),
                'featured_posts' => count(array_filter($user['posts'], fn($post) => $post['featured']))
            ];
            
            $response->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch user',
                'message' => $e->getMessage()
            ]);
        }
    }
}
