<?php
require_once __DIR__ . '/Usuario.php';

class Cliente extends Usuario
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT id_usuario AS id_cliente, id_usuario, nombre, correo, rol, fecha_registro
             FROM usuarios WHERE rol = 'cliente' ORDER BY id_usuario"
        );
    }

    public static function obtenerPorEmail($email)
    {
        $rows = self::ExecuteQuery(
            "SELECT * FROM usuarios WHERE correo = :correo AND rol = 'cliente'",
            ['correo' => $email]
        );
        return $rows ? $rows[0] : null;
    }

    public static function crearConAutenticacion($nombre, $email, $password)
    {
        $created = self::crear($nombre, $email, $password, 'cliente');
        return ['mensaje' => 'OK', 'id' => $created['id_usuario']];
    }

    public static function updateCliente($id, $nombre, $correo, $contrasena = '')
    {
        $params = ['id' => $id, 'nombre' => $nombre, 'correo' => $correo];
        $passwordSql = '';
        if ($contrasena !== '') {
            $passwordSql = ', contrasena = :contrasena';
            $params['contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
        }
        return self::ExecuteStatement(
            "UPDATE usuarios SET nombre = :nombre, correo = :correo{$passwordSql}
             WHERE id_usuario = :id AND rol = 'cliente'",
            $params
        );
    }

    public static function deleteCliente($id)
    {
        return self::ExecuteStatement(
            "DELETE FROM usuarios WHERE id_usuario = :id AND rol = 'cliente'",
            ['id' => $id]
        );
    }
}
