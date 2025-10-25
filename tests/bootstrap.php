<?php
/**
 * Bootstrap file for PHPUnit tests
 *
 * This file is loaded before all tests and sets up the testing environment
 */

// Define test mode constant
define('DOLIBARR_TEST_MODE', true);

// Mock Dolibarr constants if not already defined
if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}

// Autoloader for test classes
spl_autoload_register(function ($class) {
    // Repository classes
    if (strpos($class, 'Repository') !== false) {
        $file = __DIR__ . '/../class/repositories/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Mock DoliDB class for testing
 */
class DoliDB
{
    private $queries = [];
    private $results = [];

    public function setMockResult($sql, $result)
    {
        $this->results[md5($sql)] = $result;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        $key = md5($sql);

        if (isset($this->results[$key])) {
            return $this->results[$key];
        }

        return true;
    }

    public function fetch_object($resql)
    {
        if (is_object($resql)) {
            return $resql;
        }
        return false;
    }

    public function fetch_array($resql)
    {
        if (is_array($resql)) {
            return $resql;
        }
        return false;
    }

    public function free($resql)
    {
        return true;
    }

    public function num_rows($resql)
    {
        if (is_array($resql)) {
            return count($resql);
        }
        return 0;
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function lastQuery()
    {
        return end($this->queries);
    }
}
