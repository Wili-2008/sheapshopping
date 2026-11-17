<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Usuario extends ConexionPDO
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT id_usuario, nombre, correo, rol, fecha_registro FROM usuarios ORDER BY id_usuario"
        );
    }

    public static function obtenerPorCorreo($correo)
    {
        $rows = self::ExecuteQuery("SELECT * FROM usuarios WHERE correo = :correo", ['correo' => $correo]);
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorEmail($email)
    {
        return self::obtenerPorCorreo($email);
    }

    public static function obtenerPorEmailOrNombre($identifier)
    {
        $rows = self::ExecuteQuery(
            "SELECT * FROM usuarios WHERE correo = :correo OR nombre = :nombre",
            ['correo' => $identifier, 'nombre' => $identifier]
        );
        return $rows ? $rows[0] : null;
    }

    public static function crear($nombre, $correo, $contrasena, $rol = 'cliente')
    {
        $id = self::InsertarSql(
            "INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (:nombre, :correo, :contrasena, :rol)",
            [
                'nombre' => $nombre,
                'correo' => $correo,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                'rol' => $rol,
            ]
        );
        return ['id_usuario' => $id];
    }

    public static function updateUsuario($id_usuario, $nombre, $correo, $contrasena = '', $rol = null)
    {
        $params = [
            'id_usuario' => $id_usuario,
            'nombre' => $nombre,
            'correo' => $correo,
        ];
        $roleSql = '';
        if ($rol !== null) {
            $roleSql = ', rol = :rol';
            $params['rol'] = $rol;
        }
        $passwordSql = '';
        if ($contrasena !== '') {
            $passwordSql = ', contrasena = :contrasena';
            $params['contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
        }
        return self::ExecuteStatement(
            "UPDATE usuarios SET nombre = :nombre, correo = :correo{$roleSql}{$passwordSql} WHERE id_usuario = :id_usuario",
            $params
        );
    }

    public static function deleteUsuario($id)
    {
        return self::ExecuteStatement("DELETE FROM usuarios WHERE id_usuario = :id", ['id' => $id]);
    }
}
