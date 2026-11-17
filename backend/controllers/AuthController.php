<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../config/config.php';

class AuthController extends ApiController
{
    public function loginUsuario()
    {
        $this->authenticate(['admin']);
    }

    public function loginCliente()
    {
        $this->authenticate(['cliente']);
    }

    public function login()
    {
        $this->authenticate(['admin', 'cliente']);
    }

    private function authenticate(array $roles)
    {
        $this->run(function () use ($roles) {
            $data = $this->input();
            $identifier = trim((string) ($data['correo'] ?? $data['email'] ?? $data['username'] ?? ''));
            $password = (string) ($data['contrasena'] ?? $data['password'] ?? '');
            if ($identifier === '' || $password === '') {
                throw new InvalidArgumentException('Correo/usuario y contraseña son obligatorios.');
            }

            $user = Usuario::obtenerPorEmailOrNombre($identifier);
            if (!$user || !in_array($user['rol'], $roles, true) || !password_verify($password, $user['contrasena'])) {
                throw new AuthenticationException('Usuario o contraseña incorrectos.');
            }

            $tipo = $user['rol'] === 'cliente' ? 'cliente' : 'usuario';
            return [
                'success' => true,
                'message' => 'Sesión iniciada correctamente.',
                'token' => $this->generarToken([
                    'id' => (int) $user['id_usuario'],
                    'id_usuario' => (int) $user['id_usuario'],
                    'tipo' => $tipo,
                    'rol' => $user['rol'],
                    'correo' => $user['correo'],
                ]),
                'user' => [
                    'id_usuario' => (int) $user['id_usuario'],
                    'id' => (int) $user['id_usuario'],
                    'nombre' => $user['nombre'],
                    'correo' => $user['correo'],
                    'email' => $user['correo'],
                    'rol' => $user['rol'],
                    'tipo' => $tipo,
                ],
            ];
        });
    }

    public function registrarCliente()
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

            $created = Cliente::crearConAutenticacion(trim($data['nombre']), $correo, $password);
            return [
                'success' => true,
                'message' => 'Cliente registrado correctamente.',
                'token' => $this->generarToken([
                    'id' => $created['id'],
                    'id_usuario' => $created['id'],
                    'tipo' => 'cliente',
                    'rol' => 'cliente',
                    'correo' => $correo,
                ]),
                'user' => [
                    'id_usuario' => $created['id'],
                    'id' => $created['id'],
                    'nombre' => trim($data['nombre']),
                    'correo' => $correo,
                    'email' => $correo,
                    'tipo' => 'cliente',
                    'rol' => 'cliente',
                ],
            ];
        }, 201);
    }

    public function logout()
    {
        $this->respond(['success' => true, 'message' => 'Sesión cerrada correctamente.']);
    }

    public function verifyToken()
    {
        $payload = AuthToken::fromRequest();
        if (!$payload) {
            $this->respond(['authenticated' => false], 401);
            return;
        }
        $this->respond(['authenticated' => true, 'user' => $payload]);
    }

    private function generarToken(array $payload)
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::base64UrlEncode(json_encode($payload + ['iat' => time(), 'exp' => time() + 86400]));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, JWT_SECRET, true));
        return $header . '.' . $body . '.' . $signature;
    }

    private static function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
