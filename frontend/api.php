<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['imagen'])) {
    $filename = (string) $_GET['imagen'];
    if ($filename !== basename($filename) || !preg_match('/^[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$/i', $filename)) {
        http_response_code(400);
        exit;
    }

    $imagePath = __DIR__ . '/../backend/imagenes/' . $filename;
    if (!is_file($imagePath)) {
        http_response_code(404);
        exit;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($imagePath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($imagePath);
    exit;
}

// WAMP publica frontend/ como raíz del sitio; carga el router del backend
// desde su ubicación original para que el API no dependa de un Alias de Apache.
require __DIR__ . '/../backend/routes/api.php';
