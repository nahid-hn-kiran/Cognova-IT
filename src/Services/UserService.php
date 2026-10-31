<?php

namespace App\Services;

use App\Config\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * User & Role Management Service
 *
 * Provides administrative oversight of staff accounts, role assignments,
 * password hashing, status toggling, and lockout prevention.
 */
class UserService
{
    /**
     * Retrieve all users joined with role metadata
     *
     * @return array<array<string, mixed>>
     */
    public function getAll(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query('
            SELECT 
                u.id,
                u.role_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                r.name AS role_name,
                r.slug AS role_slug,
                r.description AS role_description
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            ORDER BY u.id ASC
        ');

        return $stmt->fetchAll();
    }

    /**
     * Retrieve a single user by ID
     */
    public function getById(int $id): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT 
                u.id,
                u.role_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                r.name AS role_name,
                r.slug AS role_slug
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Retrieve all system roles
     *
     * @return array<array<string, mixed>>
     */
    public function getRoles(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT id, name, slug, description FROM roles ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    /**
     * Create a new staff account
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     role_id: int,
     *     status?: string
     * } $data
     * @return array
     */
    public function create(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $email = trim(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = (string)($data['password'] ?? '');
        $roleId = (int)($data['role_id'] ?? 0);
        $status = in_array($data['status'] ?? '', ['active', 'inactive', 'suspended'], true) 
            ? $data['status'] 
            : 'active';

        if (empty($name)) {
            throw new InvalidArgumentException('User full name is required.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid work email address is required.');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters in length.');
        }

        if ($roleId <= 0) {
            throw new InvalidArgumentException('A valid system role must be selected.');
        }

        $pdo = Database::getConnection();

        // Check email uniqueness
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            throw new InvalidArgumentException("A user account with email '{$email}' already exists.");
        }

        // Verify role existence
        $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE id = :id LIMIT 1');
        $roleStmt->execute([':id' => $roleId]);
        if (!$roleStmt->fetch()) {
            throw new InvalidArgumentException("Selected role ID #{$roleId} does not exist.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare('
            INSERT INTO users (
                role_id, name, email, password, status, created_at, updated_at
            ) VALUES (
                :role_id, :name, :email, :password, :status, NOW(), NOW()
            )
        ');

        $stmt->execute([
            ':role_id' => $roleId,
            ':name' => $name,
            ':email' => $email,
            ':password' => $hash,
            ':status' => $status,
        ]);

        $newId = (int)$pdo->lastInsertId();
        return $this->getById($newId);
    }

    /**
     * Update an existing staff account
     *
     * @param int $id User ID to update
     * @param array $data Fields to update
     * @param int $actingAdminId ID of the logged in administrator performing the action
     * @return array
     */
    public function update(int $id, array $data, int $actingAdminId): array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new InvalidArgumentException("User account #{$id} not found.");
        }

        $name = trim($data['name'] ?? $existing['name']);
        $email = trim(filter_var($data['email'] ?? $existing['email'], FILTER_SANITIZE_EMAIL));
        $roleId = isset($data['role_id']) ? (int)$data['role_id'] : (int)$existing['role_id'];
        $status = isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'suspended'], true)
            ? $data['status']
            : $existing['status'];

        if (empty($name)) {
            throw new InvalidArgumentException('User full name cannot be blank.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Valid email is required.');
        }

        // Lockout protection: Cannot suspend or demote yourself
        if ($id === $actingAdminId) {
            if ($status !== 'active') {
                throw new InvalidArgumentException('Lockout protection: You cannot deactivate or suspend your own active administrator account.');
            }
            if ($roleId !== (int)$existing['role_id'] && strtolower($existing['role_slug']) === 'admin') {
                throw new InvalidArgumentException('Lockout protection: You cannot demote yourself away from the Admin role.');
            }
        }

        $pdo = Database::getConnection();

        // Check email uniqueness if email changed
        if ($email !== $existing['email']) {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $checkStmt->execute([':email' => $email, ':id' => $id]);
            if ($checkStmt->fetch()) {
                throw new InvalidArgumentException("Email '{$email}' is already in use by another account.");
            }
        }

        $passwordSql = '';
        $params = [
            ':name' => $name,
            ':email' => $email,
            ':role_id' => $roleId,
            ':status' => $status,
            ':id' => $id,
        ];

        // Optional password reset
        if (!empty($data['password'])) {
            $password = (string)$data['password'];
            if (strlen($password) < 6) {
                throw new InvalidArgumentException('Password must be at least 6 characters in length.');
            }
            $passwordSql = ', password = :password';
            $params[':password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $stmt = $pdo->prepare("
            UPDATE users SET 
                name = :name,
                email = :email,
                role_id = :role_id,
                status = :status
                {$passwordSql},
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute($params);
        return $this->getById($id);
    }

    /**
     * Quick status toggle (active <-> suspended)
     */
    public function toggleStatus(int $id, string $status, int $actingAdminId): array
    {
        if ($id === $actingAdminId && $status !== 'active') {
            throw new InvalidArgumentException('Lockout protection: You cannot suspend your own administrative session.');
        }

        return $this->update($id, ['status' => $status], $actingAdminId);
    }
}

