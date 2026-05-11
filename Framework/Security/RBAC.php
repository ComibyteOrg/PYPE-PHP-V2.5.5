<?php

namespace Framework\Security;

use Framework\Helper\DB;
use Framework\Logging\Logger;

/**
 * RBAC — Role-Based Access Control
 * Provides role and permission management with policies and gates.
 */
class RBAC
{
    private static array $roles = [];
    private static array $permissions = [];
    private static array $rolePermissions = [];
    private static bool $initialized = false;

    /* ============================================================
       INITIALIZATION
       ============================================================ */

    /**
     * Initialize from database or use in-memory definitions.
     */
    public static function init(bool $useDatabase = false): void
    {
        if ($useDatabase) {
            self::ensureTables();
            self::loadFromDatabase();
        }
        self::$initialized = true;
    }

    /* ============================================================
       ROLE MANAGEMENT
       ============================================================ */

    /**
     * Define a role with optional description.
     */
    public static function defineRole(string $role, string $description = ''): void
    {
        self::$roles[$role] = [
            'name' => $role,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!isset(self::$rolePermissions[$role])) {
            self::$rolePermissions[$role] = [];
        }
    }

    /**
     * Check if a role exists.
     */
    public static function hasRole(string $role): bool
    {
        return isset(self::$roles[$role]);
    }

    /**
     * Get all defined roles.
     */
    public static function getRoles(): array
    {
        return array_values(self::$roles);
    }

    /**
     * Delete a role.
     */
    public static function deleteRole(string $role): void
    {
        unset(self::$roles[$role]);
        unset(self::$rolePermissions[$role]);
    }

    /* ============================================================
       PERMISSION MANAGEMENT
       ============================================================ */

    /**
     * Define a permission.
     */
    public static function definePermission(string $permission, string $description = ''): void
    {
        self::$permissions[$permission] = [
            'name' => $permission,
            'description' => $description,
        ];
    }

    /**
     * Grant a permission to a role.
     */
    public static function grant(string $role, string $permission): void
    {
        if (!isset(self::$rolePermissions[$role])) {
            self::$rolePermissions[$role] = [];
        }

        if (!in_array($permission, self::$rolePermissions[$role])) {
            self::$rolePermissions[$role][] = $permission;
        }
    }

    /**
     * Revoke a permission from a role.
     */
    public static function revoke(string $role, string $permission): void
    {
        if (isset(self::$rolePermissions[$role])) {
            self::$rolePermissions[$role] = array_filter(
                self::$rolePermissions[$role],
                fn($p) => $p !== $permission
            );
        }
    }

    /**
     * Check if a role has a specific permission.
     */
    public static function roleHasPermission(string $role, string $permission): bool
    {
        if ($role === 'superadmin') return true;

        return in_array($permission, self::$rolePermissions[$role] ?? []);
    }

    /**
     * Get all permissions for a role.
     */
    public static function getRolePermissions(string $role): array
    {
        return self::$rolePermissions[$role] ?? [];
    }

    /* ============================================================
       USER PERMISSION CHECKS
       ============================================================ */

    /**
     * Assign a role to a user.
     */
    public static function assignRole(int $userId, string $role): bool
    {
        self::ensureTables();

        try {
            // Check if assignment exists
            $existing = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role', $role)
                ->first();

            if ($existing) {
                return false;
            }

            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role' => $role,
                'assigned_at' => date('Y-m-d H:i:s'),
                'assigned_by' => $_SESSION['user_id'] ?? null,
            ]);

            Logger::info('Role assigned', ['user_id' => $userId, 'role' => $role]);
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to assign role', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Remove a role from a user.
     */
    public static function removeRole(int $userId, string $role): bool
    {
        try {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role', $role)
                ->delete([]);

            Logger::info('Role removed', ['user_id' => $userId, 'role' => $role]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all roles for a user.
     */
    public static function getUserRoles(int $userId): array
    {
        try {
            $roles = DB::table('user_roles')
                ->where('user_id', $userId)
                ->pluck('role');
            return $roles;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a user has a specific permission.
     */
    public static function userHasPermission(int $userId, string $permission): bool
    {
        $roles = self::getUserRoles($userId);

        foreach ($roles as $role) {
            if (self::roleHasPermission($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has a specific role.
     */
    public static function userHasRole(int $userId, string $role): bool
    {
        return in_array($role, self::getUserRoles($userId));
    }

    /* ============================================================
       GATE — CLOSURE-BASED AUTHORIZATION
       ============================================================ */

    private static array $gates = [];

    /**
     * Define a gate (closure-based authorization).
     */
    public static function gate(string $ability, callable $callback): void
    {
        self::$gates[$ability] = $callback;
    }

    /**
     * Check a gate for the current user.
     */
    public static function allows(string $ability, mixed $subject = null): bool
    {
        if (!isset(self::$gates[$ability])) {
            return false;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return false;
        }

        return (self::$gates[$ability])($userId, $subject);
    }

    /**
     * Deny version — inverse of allows.
     */
    public static function denies(string $ability, mixed $subject = null): bool
    {
        return !self::allows($ability, $subject);
    }

    /**
     * Authorize — throws exception if not allowed.
     */
    public static function authorize(string $ability, mixed $subject = null): void
    {
        if (self::denies($ability, $subject)) {
            throw new \Exception("Unauthorized: {$ability}");
        }
    }

    /**
     * Check if user can perform action (alias for allows).
     */
    public static function can(string $ability, mixed $subject = null): bool
    {
        return self::allows($ability, $subject);
    }

    /* ============================================================
       DATABASE PERSISTENCE
       ============================================================ */

    /**
     * Save a role-permission pair to the database.
     */
    public static function saveRolePermission(string $role, string $permission): bool
    {
        self::ensureTables();

        try {
            DB::table('role_permissions')->insert([
                'role' => $role,
                'permission' => $permission,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            self::grant($role, $permission);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load all role-permission pairs from the database.
     */
    private static function loadFromDatabase(): void
    {
        try {
            $records = DB::table('role_permissions')->get();

            foreach ($records as $record) {
                self::grant($record['role'], $record['permission']);
            }

            // Load roles
            $roles = DB::table('roles')->get();
            foreach ($roles as $role) {
                self::defineRole($role['name'], $role['description'] ?? '');
            }

            // Load permissions
            $permissions = DB::table('permissions')->get();
            foreach ($permissions as $permission) {
                self::definePermission($permission['name'], $permission['description'] ?? '');
            }
        } catch (\Exception $e) {
            // Tables may not exist yet
        }
    }

    private static function ensureTables(): void
    {
        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                description TEXT,
                created_at DATETIME NOT NULL
            )");
        } catch (\Exception $e) {}

        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                description TEXT,
                created_at DATETIME NOT NULL
            )");
        } catch (\Exception $e) {}

        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(100) NOT NULL,
                permission VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY unique_role_permission (role, permission)
            )");
        } catch (\Exception $e) {}

        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS user_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                role VARCHAR(100) NOT NULL,
                assigned_at DATETIME NOT NULL,
                assigned_by INT,
                UNIQUE KEY unique_user_role (user_id, role)
            )");
        } catch (\Exception $e) {}
    }

    /**
     * Seed default roles and permissions.
     */
    public static function seedDefaults(): void
    {
        // Roles
        self::defineRole('superadmin', 'Full system access');
        self::defineRole('admin', 'Administrative access');
        self::defineRole('moderator', 'Content moderation');
        self::defineRole('user', 'Regular user');
        self::defineRole('guest', 'Unauthenticated user');

        // Permissions
        $perms = [
            'view_dashboard', 'manage_users', 'delete_users',
            'create_posts', 'edit_posts', 'delete_posts', 'publish_posts',
            'create_comments', 'delete_comments', 'moderate_comments',
            'manage_settings', 'view_analytics', 'manage_roles',
            'upload_files', 'delete_files',
        ];

        foreach ($perms as $perm) {
            self::definePermission($perm);
        }

        // Grant all to superadmin
        foreach ($perms as $perm) {
            self::grant('superadmin', $perm);
        }

        // Admin permissions
        foreach (['view_dashboard', 'manage_users', 'create_posts', 'edit_posts', 'delete_posts', 'publish_posts', 'delete_comments', 'manage_settings', 'view_analytics', 'upload_files', 'delete_files'] as $perm) {
            self::grant('admin', $perm);
        }

        // Moderator permissions
        foreach (['view_dashboard', 'delete_comments', 'moderate_comments', 'create_posts', 'edit_posts'] as $perm) {
            self::grant('moderator', $perm);
        }

        // User permissions
        foreach (['view_dashboard', 'create_posts', 'edit_posts', 'create_comments', 'upload_files'] as $perm) {
            self::grant('user', $perm);
        }
    }
}
