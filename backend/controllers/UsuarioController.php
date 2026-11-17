<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Usuario.php';

class UsuarioController extends ApiController
{
    public function listarUsuarios()
    {
        $this->run(function () {
            return Usuario::obtenerTodos();
        });
    }

    public function agregarUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $correo = $data['correo'] ?? $data['email'] ?? null;
            if (!isset($data['nombre'], $correo) || !isset($data['contrasena']) && !isset($data['password'])) {
                throw new InvalidArgumentException('Nombre, correo y contraseña son obligatorios.');
            }
            $password = $data['contrasena'] ?? $data['password'];
            $rol = $data['rol'] ?? 'cliente';
            if (trim((string) $data['nombre']) === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 1) {
                throw new InvalidArgumentException('Nombre, correo válido y contraseña son obligatorios.');
            }
            if (!in_array($rol, ['admin', 'cliente'], true)) {
                throw new InvalidArgumentException('El rol debe ser admin o cliente.');
            }
            $created = Usuario::crear(trim($data['nombre']), $correo, $password, $rol);
            return ['success' => true, 'data' => $created];
        }, 201);
    }

    public function updateUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_usuario', 'nombre']);
            $correo = $data['correo'] ?? $data['email'] ?? null;
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Se requiere un correo válido.');
            }
            $rol = $data['rol'] ?? null;
            if ($rol !== null && !in_array($rol, ['admin', 'cliente'], true)) {
                throw new InvalidArgumentException('El rol debe ser admin o cliente.');
            }
            return ['success' => true, 'affected' => Usuario::updateUsuario(
                $this->positiveInteger($data['id_usuario'], 'id_usuario'),
                trim($data['nombre']),
                $correo,
                $data['contrasena'] ?? $data['password'] ?? '',
                $rol
            )];
        });
    }

    public function deleteUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $id = $data['id_usuario'] ?? $_GET['id_usuario'] ?? null;
            return ['success' => true, 'affected' => Usuario::deleteUsuario($this->positiveInteger($id, 'id_usuario'))];
        });
    }
}
