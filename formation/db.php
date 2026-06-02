<?php
if (!defined('FORMATION_DB_LOADED')) {
    define('FORMATION_DB_LOADED', true);
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = PHP_OS_FAMILY === 'Windows'
        || strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false
        || strpos($host, '::1')       !== false;
    $servername = 'localhost';
    $username   = $isLocal ? 'root'                      : 'u264396140_formation';
    $password   = $isLocal ? ''                          : 'Hondaand@1';
    $dbname     = $isLocal ? 'netcrafter_formation'      : 'u264396140_formation';
}
