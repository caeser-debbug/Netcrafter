<?php
if (!defined('ADMIN_FORMATION_DB_LOADED')) {
    define('ADMIN_FORMATION_DB_LOADED', true);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    $servername = 'localhost';
    $username   = $isLocal ? 'root'                      : 'u264396140_formation';
    $password   = $isLocal ? ''                          : 'Hondaand@1';
    $dbname     = $isLocal ? 'netcrafter_formation'      : 'u264396140_formation';
}
