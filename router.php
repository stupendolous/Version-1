<?php
// Router for PHP's built-in server:  php -S localhost:8000 router.php
// Blocks internal folders and dotfiles (like .env) from being downloaded.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(ai|lib|bin|database|storage|vendor|legacy)(/|$)|/\.|\.(sql|log|lock|json|md)$#i', $path)) {
    http_response_code(404);
    exit('Not found');
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
return false;
