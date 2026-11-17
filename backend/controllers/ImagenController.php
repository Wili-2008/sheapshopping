<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

class ImagenController extends ApiController
{
    public function subirImagen()
    {
        AuthMiddleware::esAdmin();

        $this->run(function () {
            $file = $_FILES['imagen'] ?? null;
            if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Selecciona una imagen válida para subir.');
            }
            if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('La imagen debe pesar menos de 5 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($extensions[$mime])) {
                throw new InvalidArgumentException('Usa una imagen JPG, PNG o WEBP.');
            }

            $directory = __DIR__ . '/../imagenes';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
                throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
            }

            return ['success' => true, 'imagen' => 'imagenes/' . $filename];
        }, 201);
    }
}
