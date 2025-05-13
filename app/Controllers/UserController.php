<?php
namespace Hasphp\App\Controllers;

use Hasphp\App\Core\Request;
use Hasphp\App\Core\Response;

class UserController
{
    /**
     * @summary Get paginated list of users
     * @method get
     * @path /api/users
     * @param int $page Page number
     * @param int $limit Items per page (max: 50)
     * @response {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "John Doe"},
     *     {"id": 2, "name": "Jane Smith"}
     *   ],
     *   "pagination": {"page": 1, "total": 2}
     * }
     */
    public function listUsers(Request $req, Response $res)
    {
        $page = $req->input('page', 1);
        $limit = min($req->input('limit', 10), 50);

        $dummyData = [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];

        $res->json([
            'success' => true,
            'data' => $dummyData,
            'pagination' => [
                'page' => (int) $page,
                'total' => count($dummyData)
            ]
        ]);
    }

    /**
     * @summary Create a new user
     * @method post
     * @path /api/users
     * @body {
     *   "name": "string|required",
     *   "email": "string|required|email",
     *   "password": "string|required|min:8"
     * }
     * @response 201 {
     *   "success": true,
     *   "id": 3,
     *   "message": "User created"
     * }
     */
    public function createUser(Request $req, Response $res)
    {
        $data = $req->json();

        // Dummy validation
        if (empty($data['name'])) {
            return $res->status(400)->json([
                'error' => 'Name is required'
            ]);
        }

        // Dummy "database insertion"
        $newUserId = 3; // Simulated auto-increment ID

        $res->status(201)->json([
            'success' => true,
            'id' => $newUserId,
            'message' => 'User created'
        ]);
    }

    /**
     * @summary Get user by ID
     * @method get
     * @path /api/users/{id}
     * @param int $id User ID
     * @response {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "john@example.com"
     * }
     * @response 404 {"error": "User not found"}
     */
    public function getUser(Request $req, Response $res, $id)
    {
        $dummyUsers = [
            1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
        ];

        if (!isset($dummyUsers[$id])) {
            return $res->status(404)->json([
                'error' => 'User not found'
            ]);
        }

        $res->json($dummyUsers[$id]);
    }

    /**
     * @summary Update user password
     * @method patch
     * @path /api/users/{id}/password
     * @param int $id User ID
     * @body {
     *   "current_password": "string|required",
     *   "new_password": "string|required|min:8"
     * }
     * @response {
     *   "success": true,
     *   "message": "Password updated"
     * }
     * @response 400 {"error": "Invalid current password"}
     */
    public function updatePassword(Request $req, Response $res, $id)
    {
        $data = $req->json();

        // Dummy password check
        if ($data['current_password'] !== 'valid_password') {
            return $res->status(400)->json([
                'error' => 'Invalid current password'
            ]);
        }

        // In real app, you would hash the new password here
        $res->json([
            'success' => true,
            'message' => 'Password updated'
        ]);
    }

    public function list(Request $req, Response $res)
    {
        $users = (new \Hasphp\App\Core\DB\QueryBuilder())
            ->table('users')
            ->get();

        $res->json($users);
    }
}