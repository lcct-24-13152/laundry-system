<?php
// Loads settings once. Uses config.php if it exists, otherwise the example defaults.
if (!defined('DB_HOST')) {
    $cfg = __DIR__ . '/config.php';
    require file_exists($cfg) ? $cfg : __DIR__ . '/config.example.php';
}

/** Base URL of the system, e.g. http://localhost/laundry-system (no trailing slash). */
function app_url() {
    if (APP_URL !== '') return rtrim(APP_URL, '/');
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Project root = folder above this config folder, relative to the web root
    $root   = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc    = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $path   = ($doc !== '' && strpos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
    return $scheme . '://' . $host . rtrim($path, '/');
}
