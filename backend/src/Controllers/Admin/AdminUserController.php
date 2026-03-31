<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminUserController
{
    private PDO $pdo;
    private AuthService $authService;

    public function __construct(PDO $pdo, AuthService $authService)
    {
        $this->pdo = $pdo;
        $this->authService = $authService;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $queryParams = [];

        // Search filter
        if (!empty($params['search'])) {
            $where[] = "(au.name LIKE ? OR au.email LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }

        // Role filter
        if (!empty($params['role_id'])) {
            $where[] = "au.role_id = ?";
            $queryParams[] = $params['role_id'];
        }

        // Status filter
        if (!empty($params['status'])) {
            $where[] = "au.status = ?";
            $queryParams[] = $params['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM admin_users au {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($queryParams);
        $total = (int) $stmt->fetch()['total'];

        // Get admin users
        $sql = "
            SELECT au.id, au.email, au.name, au.status, au.last_login, au.created_at,
                   r.name as role_name, r.id as role_id
            FROM admin_users au
            LEFT JOIN roles r ON au.role_id = r.id
            {$whereClause}
            ORDER BY au.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $admins = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $admins,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';
        $roleId = (int) ($data['role_id'] ?? 2);

        if (empty($email) || empty($password) || empty($name)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Email, password, and name are required'
            ], 400);
        }

        $adminId = $this->authService->createAdmin($email, $password, $name, $roleId);

        if (!$adminId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Email already exists'
            ], 409);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Admin user created',
            'admin_id' => $adminId
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $adminId = (int) $args['id'];
        $data = $request->getParsedBody();

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }

        if (isset($data['role_id'])) {
            $updates[] = "role_id = ?";
            $params[] = $data['role_id'];
        }

        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }

        if (!empty($data['password'])) {
            $updates[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'No fields to update'
            ], 400);
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $adminId;

        $sql = "UPDATE admin_users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Admin user updated'
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $adminId = (int) $args['id'];
        $currentAdminId = $request->getAttribute('admin_id');

        // Prevent self-deletion
        if ($adminId === $currentAdminId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Cannot delete your own account'
            ], 400);
        }

        $stmt = $this->pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$adminId]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Admin user deleted'
        ]);
    }

    // Roles management
    public function getRoles(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->query("
            SELECT r.*,
                   (SELECT COUNT(*) FROM admin_users WHERE role_id = r.id) as user_count
            FROM roles r
            ORDER BY r.id
        ");
        $roles = $stmt->fetchAll();

        // Get permissions for each role
        foreach ($roles as &$role) {
            $stmt = $this->pdo->prepare("SELECT resource, action FROM permissions WHERE role_id = ?");
            $stmt->execute([$role['id']]);
            $role['permissions'] = $stmt->fetchAll();
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $roles
        ]);
    }

    public function createRole(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $permissions = $data['permissions'] ?? [];

        if (empty($name)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Role name is required'
            ], 400);
        }

        // Create role
        $stmt = $this->pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $roleId = (int) $this->pdo->lastInsertId();

        // Add permissions
        if (!empty($permissions)) {
            $stmt = $this->pdo->prepare("INSERT INTO permissions (role_id, resource, action) VALUES (?, ?, ?)");
            foreach ($permissions as $perm) {
                $stmt->execute([$roleId, $perm['resource'], $perm['action']]);
            }
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Role created',
            'role_id' => $roleId
        ], 201);
    }

    public function updateRole(Request $request, Response $response, array $args): Response
    {
        $roleId = (int) $args['id'];
        $data = $request->getParsedBody();

        // Check if system role
        $stmt = $this->pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();

        if ($role && $role['is_system'] && isset($data['name'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Cannot rename system roles'
            ], 400);
        }

        // Update role
        if (isset($data['name']) || isset($data['description'])) {
            $updates = [];
            $params = [];

            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }

            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $roleId;

            $sql = "UPDATE roles SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        // Update permissions
        if (isset($data['permissions'])) {
            // Remove existing permissions
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // Add new permissions
            $stmt = $this->pdo->prepare("INSERT INTO permissions (role_id, resource, action) VALUES (?, ?, ?)");
            foreach ($data['permissions'] as $perm) {
                $stmt->execute([$roleId, $perm['resource'], $perm['action']]);
            }
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Role updated'
        ]);
    }

    public function deleteRole(Request $request, Response $response, array $args): Response
    {
        $roleId = (int) $args['id'];

        // Check if system role
        $stmt = $this->pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();

        if ($role && $role['is_system']) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Cannot delete system roles'
            ], 400);
        }

        // Check if role is in use
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM admin_users WHERE role_id = ?");
        $stmt->execute([$roleId]);
        if ($stmt->fetch()['count'] > 0) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Role is assigned to users'
            ], 400);
        }

        // Delete role and permissions
        $this->pdo->prepare("DELETE FROM permissions WHERE role_id = ?")->execute([$roleId]);
        $this->pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Role deleted'
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
