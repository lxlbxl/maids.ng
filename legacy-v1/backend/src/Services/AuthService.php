<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

class AuthService
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function login(string $phone, string $pin): ?array
    {
        $phone = $this->normalizePhone($phone);

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE phone = ? AND status = 'active'");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->logger->info("Login failed: User not found", ['phone' => $phone]);
            return null;
        }

        if (!password_verify($pin, $user['pin_hash'])) {
            $this->logger->info("Login failed: Invalid PIN", ['phone' => $phone]);
            return null;
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['user_type'] = $user['user_type'];

        $this->logger->info("User logged in", ['user_id' => $user['id']]);

        return [
            'id' => $user['id'],
            'phone' => $user['phone'],
            'user_type' => $user['user_type']
        ];
    }

    public function register(string $phone, string $pin, string $userType = 'employer'): ?array
    {
        $phone = $this->normalizePhone($phone);

        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $this->logger->info("Registration failed: Phone exists", ['phone' => $phone]);
            return null;
        }

        // Create user
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("INSERT INTO users (phone, pin_hash, user_type) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $pinHash, $userType]);

        $userId = (int)$this->pdo->lastInsertId();

        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_type'] = $userType;

        $this->logger->info("User registered", ['user_id' => $userId]);

        return [
            'id' => $userId,
            'phone' => $phone,
            'user_type' => $userType
        ];
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    public function getUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, phone, user_type, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function getUserByPhone(string $phone): ?array
    {
        $phone = $this->normalizePhone($phone);
        $stmt = $this->pdo->prepare("SELECT id, phone, user_type, status, created_at FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        return $stmt->fetch() ?: null;
    }

    public function updatePin(int $userId, string $newPin): bool
    {
        $pinHash = password_hash($newPin, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET pin_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$pinHash, $userId]);
    }

    public function adminLogin(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT au.*, r.name as role_name
            FROM admin_users au
            LEFT JOIN roles r ON au.role_id = r.id
            WHERE au.email = ? AND au.status = 'active'
        ");
        $stmt->execute([strtolower($email)]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $this->logger->info("Admin login failed: User not found", ['email' => $email]);
            return null;
        }

        // Check if account is locked
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            $this->logger->warning("Admin login failed: Account locked", ['email' => $email]);
            return null;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            // Increment login attempts
            $this->incrementLoginAttempts($admin['id']);
            $this->logger->info("Admin login failed: Invalid password", ['email' => $email]);
            return null;
        }

        // Reset login attempts and update last login
        $stmt = $this->pdo->prepare("UPDATE admin_users SET login_attempts = 0, last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$admin['id']]);

        // Get permissions
        $permissions = $this->getAdminPermissions($admin['role_id']);

        // Set session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role_id'] = $admin['role_id'];
        $_SESSION['admin_role_name'] = $admin['role_name'];
        $_SESSION['admin_permissions'] = $permissions;

        $this->logger->info("Admin logged in", ['admin_id' => $admin['id']]);

        return [
            'id' => $admin['id'],
            'email' => $admin['email'],
            'name' => $admin['name'],
            'role' => $admin['role_name'],
            'permissions' => $permissions
        ];
    }

    public function adminLogout(): void
    {
        $this->logout();
    }

    public function getAdminPermissions(int $roleId): array
    {
        $stmt = $this->pdo->prepare("SELECT resource, action FROM permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }

    public function createAdmin(string $email, string $password, string $name, int $roleId): ?int
    {
        $email = strtolower($email);

        // Check if admin exists
        $stmt = $this->pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return null;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("INSERT INTO admin_users (email, password_hash, name, role_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $passwordHash, $name, $roleId]);

        return (int)$this->pdo->lastInsertId();
    }

    private function incrementLoginAttempts(int $adminId): void
    {
        $stmt = $this->pdo->prepare("SELECT login_attempts FROM admin_users WHERE id = ?");
        $stmt->execute([$adminId]);
        $attempts = (int)$stmt->fetchColumn() + 1;

        // Lock account after 5 failed attempts for 15 minutes
        $lockedUntil = null;
        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + 900);
        }

        $stmt = $this->pdo->prepare("UPDATE admin_users SET login_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$attempts, $lockedUntil, $adminId]);
    }

    private function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, and other non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Convert +234 to 0
        if (str_starts_with($phone, '+234')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
        }

        return $phone;
    }
}
