<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Cliente.php';

class ClienteController extends ApiController
{
    public function listarClientes()
    {
        $this->run(function () {
            return Cliente::obtenerTodos();
        });
    }

    public function agregarCliente()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre']);
            $correo = trim((string) ($data['correo'] ?? $data['email'] ?? ''));
            $password = (string) ($data['contrasena'] ?? $data['password'] ?? '');
            if (trim((string) $data['nombre']) === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
                throw new InvalidArgumentException('Nombre, correo válido y contraseña de al menos 6 caracteres son obligatorios.');
            }
            if (Cliente::obtenerPorEmail($correo)) {
                throw new DomainException('El correo ya está registrado.');
            }
            return ['success' => true, 'data' => Cliente::crearConAutenticacion(trim($data['nombre']), $correo, $password)];
        }, 201);
    }

    public function updateCliente()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_cliente', 'nombre']);
            $correo = trim((string) ($data['correo'] ?? $data['email'] ?? ''));
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Se requiere un correo válido.');
            }
            return ['success' => true, 'affected' => Cliente::updateCliente(
                $this->positiveInteger($data['id_cliente'], 'id_cliente'),
                trim($data['nombre']),
                $correo,
                $data['contrasena'] ?? $data['password'] ?? ''
            )];
        });
    }

    public function deleteCliente()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Cliente::deleteCliente(
                $this->positiveInteger($data['id_cliente'] ?? null, 'id_cliente')
            )];
        });
    }
}
