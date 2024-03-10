<?php

namespace Dsewth\SimpleHRBAC\Database;

interface DriverInterface
{
    public function driverName(): string;

    /**
     * Get all permissions that match to the filter.
     */
    public function permissions(array $filter = ['*']): array;

    /**
     * Returns all available roles from the database
     */
    public function roles(array $filter = ['*']): array;

    /**
     * Returns all available subjects from the database
     */
    public function subjects(array $filter = ['*']): array;

    /**
     * Save permissions to the database
     */
    public function savePermissions(array $permissions): void;
}
