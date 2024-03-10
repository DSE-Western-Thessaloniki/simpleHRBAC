<?php

namespace Dsewth\SimpleHRBAC;

use Dsewth\SimpleHRBAC\Database\Database;
use Dsewth\SimpleHRBAC\Database\DriverInterface;
use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;

class RBAC
{
    private static RBAC $instance;

    protected Database $db;

    protected array $roles;

    protected function __construct(DriverInterface $databaseDriver)
    {
        $this->db = new Database($databaseDriver);
    }

    public static function initialize(DriverInterface $driver): RBAC
    {
        if (! isset(self::$instance)) {
            self::$instance = new self($driver);

            return self::$instance;
        } else {
            self::$instance->setDatabaseDriver($driver);

            return self::$instance;
        }
    }

    private function setDatabaseDriver($driver)
    {
        $this->db = new Database($driver);
    }

    public static function getInstance(): RBAC
    {
        if (! isset(self::$instance)) {
            throw new RBACException('HRBAC not initialized! Please use initialize() instead.');
        }

        return self::$instance;
    }

    public function database(): Database
    {
        return $this->db;
    }

    /**
     * Get all permissions
     *
     * @return array<\Dsewth\SimpleHRBAC\Models\Permission>
     */
    public function getAllPermissions(): array
    {
        $result = array_map(function ($row) {
            return Permission::fromRow($row);
        }, $this->db->getPermissions());

        return $result;
    }

    /**
     * Returns all available roles from the database
     *
     * @return array<\Dsewth\SimpleHRBAC\Models\Role>
     */
    public function getAllRoles(): array
    {
        $result = array_map(function ($row) {
            return Role::fromRow($row);
        }, $this->db->getRoles());

        return $result;
    }

    /**
     * Returns all available subjects from the database
     *
     * @return array<\Dsewth\SimpleHRBAC\Models\Subject>
     */
    public function getAllSubjects(): array
    {
        $result = array_map(function ($row) {
            return Subject::fromRow($row);
        }, $this->db->getSubjects());

        return $result;
    }

    public function getSubjectById(string|int $id): ?Subject
    {
        $rows = $this->db->getSubjects(['id' => $id]);

        if (empty($rows)) {
            return null;
        }

        return Subject::fromRow($rows[0]);
    }

    /**
     * @return array<Subject>
     */
    public function getSubjectsByName(string $name): array
    {
        $rows = $this->db->getSubjects(['name' => ['like', $name]]);

        return array_map(function ($row) {
            return Subject::fromRow($row);
        }, $rows);
    }

    public function addRole($role)
    {

    }

    public function removeRole($role)
    {

    }

    public function addPermission($permission)
    {

    }

    public function removePermission($permission)
    {

    }

    public function hasPermission($role, $permission)
    {

    }
}
