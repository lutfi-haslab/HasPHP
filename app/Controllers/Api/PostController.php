<?php

namespace Hasphp\App\Controllers\Api;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;
use Hasphp\App\Models\Post;
use Hasphp\App\Models\User;

class PostController
{
    /**
     * Display a listing of posts.
     */
    public function index($request, $response)
    {
        try {
            $db = $request->app->resolve('db');
            
            // Get query parameters
            $limit = min((int)($request->query['limit'] ?? 10), 50);
            $offset = (int)($request->query['offset'] ?? 0);
            $featured = $request->query['featured'] ?? null;
            $tag = $request->query['tag'] ?? null;
            
            // Build query
            $query = $db->table('posts')
                ->select([
                    'posts.*',
                    'users.name as author_name',
                    'users.avatar as author_avatar'
                ])
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.published', '=', 1)
                ->orderBy('posts.created_at', 'desc');
            
            // Apply filters
            if ($featured !== null) {
                $query->where('posts.featured', '=', (bool)$featured);
            }
            
            if ($tag) {
                $query->join('post_tags', 'post_tags.post_id', '=', 'posts.id')
                     ->join('tags', 'tags.id', '=', 'post_tags.tag_id')
                     ->where('tags.slug', '=', $tag);
            }
            
            // Get total count for pagination
            $totalQuery = clone $query;
            $total = $totalQuery->count();
            
            // Get posts
            $posts = $query->limit($limit)->offset($offset)->get();
            
            // Get tags for each post
            foreach ($posts as &$post) {
                $post['tags'] = $db->table('tags')
                    ->select(['tags.name', 'tags.slug', 'tags.color'])
                    ->join('post_tags', 'post_tags.tag_id', '=', 'tags.id')
                    ->where('post_tags.post_id', '=', $post['id'])
                    ->get();
            }
            
            $response->json([
                'success' => true,
                'data' => $posts,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch posts',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Display the specified post.
     */
    public function show($request, $response, $slug)
    {
        try {
            $db = $request->app->resolve('db');
            
            // Get post with author info
            $post = $db->table('posts')
                ->select([
                    'posts.*',
                    'users.name as author_name',
                    'users.avatar as author_avatar',
                    'users.bio as author_bio'
                ])
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.slug', '=', $slug)
                ->where('posts.published', '=', 1)
                ->first();
            
            if (!$post) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'Post not found'
                ]);
                return;
            }
            
            // Get tags
            $post['tags'] = $db->table('tags')
                ->select(['tags.name', 'tags.slug', 'tags.color'])
                ->join('post_tags', 'post_tags.tag_id', '=', 'tags.id')
                ->where('post_tags.post_id', '=', $post['id'])
                ->get();
            
            // Get comments with author info
            $post['comments'] = $db->table('comments')
                ->select([
                    'comments.*',
                    'users.name as author_name',
                    'users.avatar as author_avatar'
                ])
                ->join('users', 'users.id', '=', 'comments.user_id')
                ->where('comments.post_id', '=', $post['id'])
                ->where('comments.approved', '=', 1)
                ->orderBy('comments.created_at', 'asc')
                ->get();
            
            // Increment views count
            $db->table('posts')
                ->where('id', '=', $post['id'])
                ->update(['views_count' => $post['views_count'] + 1]);
            
            $response->json([
                'success' => true,
                'data' => $post
            ]);
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to fetch post',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Store a newly created post.
     */
    public function store($request, $response)
    {
        try {
            $data = $request->json();
            
            // Validate required fields
            if (empty($data['title']) || empty($data['content']) || empty($data['user_id'])) {
                $response->status(400)->json([
                    'success' => false,
                    'error' => 'Missing required fields: title, content, user_id'
                ]);
                return;
            }
            
            $db = $request->app->resolve('db');
            
            // Generate slug from title
            $slug = $this->generateSlug($data['title']);
            
            // Create post
            $postData = [
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $data['content'],
                'excerpt' => $data['excerpt'] ?? $this->generateExcerpt($data['content']),
                'user_id' => $data['user_id'],
                'published' => $data['published'] ?? 0,
                'featured' => $data['featured'] ?? 0,
                'views_count' => 0,
                'published_at' => isset($data['published']) && $data['published'] ? date('Y-m-d H:i:s') : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $db->table('posts')->insert($postData);
            
            if ($result) {
                $postId = $db->connection()->lastInsertId();
                
                // Handle tags if provided
                if (!empty($data['tags']) && is_array($data['tags'])) {
                    foreach ($data['tags'] as $tagId) {
                        $db->table('post_tags')->insert([
                            'post_id' => $postId,
                            'tag_id' => $tagId,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                // Fetch the created post
                $newPost = $db->table('posts')
                    ->select([
                        'posts.*',
                        'users.name as author_name'
                    ])
                    ->join('users', 'users.id', '=', 'posts.user_id')
                    ->where('posts.id', '=', $postId)
                    ->first();
                
                $response->status(201)->json([
                    'success' => true,
                    'data' => $newPost
                ]);
            } else {
                throw new \Exception('Failed to create post');
            }
        } catch (\Exception $e) {
            $response->status(500)->json([
                'success' => false,
                'error' => 'Failed to create post',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate a URL-friendly slug from a title.
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return substr($slug, 0, 100);
    }
    
    /**
     * Generate an excerpt from content.
     */
    private function generateExcerpt(string $content, int $length = 150): string
    {
        $text = strip_tags($content);
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
            $text = substr($text, 0, strrpos($text, ' ')) . '...';
        }
        return $text;
    }
}
