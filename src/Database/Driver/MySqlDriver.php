<?php

namespace Dsewth\SimpleHRBAC\Database\Driver;

use Dsewth\SimpleHRBAC\Database\DriverInterface;
use Dsewth\SimpleHRBAC\Exceptions\RBACException;

class MysqlDriver implements DriverInterface
{
    private static $instance;

    private \mysqli $connection;

    private $prefix;

    private \mysqli_stmt $statement;

    protected function __construct(\mysqli $connection, string $prefix = '')
    {
        $this->connection = $connection;
        $this->prefix = $prefix;
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * Singletons should not be restorable from strings.
     */
    public function __wakeup()
    {
        throw new RBACException('Cannot unserialize a singleton.');
    }

    public static function initialize(\mysqli $connection, string $prefix = ''): MysqlDriver
    {
        if (! isset(self::$instance)) {
            self::$instance = new static($connection, $prefix);
        }

        return self::$instance;
    }

    public static function getInstance(): MysqlDriver
    {
        if (self::$instance === null) {
            throw new RBACException('Database not initialized.');
        }

        return self::$instance;
    }

    public function connection(): \mysqli
    {
        return $this->connection;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function statement(): \mysqli_stmt
    {
        return $this->statement;
    }

    public function fastQuery(string $query)
    {
        $result = $this->connection->query($query);

        if ($result === false) {
            throw new \ErrorException(
                "Error running query: '$query'. Mysql error: ".$this->connection->error,
                $this->connection->errno
            );
        }

        return $result;
    }

    /**
     * Execute the query using the parameters passed. It returns a statement
     * for chaining functions.
     *
     * @param  string  $query  The query to be executed.
     * @param  array  $params  An array of parameters to be bound to the query
     * @return mysqli_stmt
     *
     * @throws ErrorException Αν αποτύχει η προετοιμασία του ερωτήματος
     */
    public function query(string $query, array $params = [])
    {
        $result = $this->connection->prepare($query);

        if ($result === false) {
            throw new \ErrorException(
                "Error preparing query: '$query'. Mysql error: ".$this->connection->error,
                $this->connection->errno
            );
        }

        $this->statement = $result;

        // Δεν χρειάζεται να ελέγξουμε για σφάλμα γιατί γίνεται ήδη
        // έλεγχος μέσα στην execute
        $this->execute($params);

        // Επιστρέφουμε το statement για να πετύχουμε αλυσιδωτή εκτέλεση συναρτήσεων
        return $this->statement;
    }

    private function getType(array $params)
    {
        return str_repeat('s', count($params));
    }

    public function execute(array $params = [])
    {
        if (PHP_VERSION > '8.1') {
            $result = $this->statement->execute($params);
        } else { // Πατέντα για παλιά PHP
            if (count($params)) {
                $result = $this->statement->bind_param($this->getType($params), ...$params);

                if ($result === false) {
                    throw new \ErrorException(
                        'Failed to bind parameters: '.print_r($params, true).'. Mysql error: '.$this->connection->error,
                        $this->connection->errno
                    );
                }
            }

            $result = $this->statement->execute();
        }

        if ($result === false) {
            throw new \ErrorException(
                "Failed execution of query with params: '".print_r($params, true)."'. Mysql error: ".$this->connection->error,
                $this->connection->errno
            );
        }

        return $result;
    }

    public function prepare(string $query)
    {
        $this->statement = $this->connection->prepare($query);
        if ($this->statement === false) {
            throw new \ErrorException(
                "Error preparing query: '$query'. Mysql error: ".$this->connection->error,
                $this->connection->errno
            );
        }

        return $this->statement;
    }

    public function close_statement()
    {
        $result = $this->statement->close();

        return $result;
    }

    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        if (isset($name)) {
            return $this->connection->begin_transaction($flags, $name);
        } else {
            return $this->connection->begin_transaction($flags);
        }
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        if (isset($name)) {
            return $this->connection->commit($flags, $name);
        } else {
            return $this->connection->commit($flags);
        }
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        if (isset($name)) {
            return $this->connection->rollback($flags, $name);
        } else {
            return $this->connection->rollback($flags);
        }
    }

    // public function getRoles(): array
    // {
    //     if ($this->roles !== null) {
    //         return $this->roles;
    //     }

    //     $roles = [];

    //     $result = $this->db->query(
    //         "SELECT * FROM `{$this->db->prefix()}_roles`"
    //     );

    //     if ($result === false) {
    //         throw new RBACException('Could not get roles');
    //     }

    //     $rows = $result->fetch_all(MYSQLI_ASSOC);

    //     foreach ($rows as $row) {
    //         array_push(
    //             $roles,
    //             Role::fromRow($row)
    //         );
    //     }

    //     $this->roles = $roles;

    //     return $this->roles;
    // }
}
