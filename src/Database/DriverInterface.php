<?php

namespace Dsewth\SimpleHRBAC\Database;

interface DriverInterface
{
    public function driverName(): string;

    /**
     * Returns all available object from the database that match the filter
     */
    public function select(string $table, array $filter = ['*']): array;

    /**
     * Save rows to the database
     */
    public function saveRows(string $table, array $columns, array $rows): array;

    /**
     * Save rows to the database
     */
    public function deleteRows(string $table, array $columns, array $rows): array;
}
