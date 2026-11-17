<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Categoria extends ConexionPDO
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
    }

    public static function crear($nombre)
    {
        return self::InsertarSql("INSERT INTO categorias (nombre) VALUES (:nombre)", ['nombre' => $nombre]);
    }

    public static function updateCategoria($id_categoria, $nombre)
    {
        return self::ExecuteStatement(
            "UPDATE categorias SET nombre = :nombre WHERE id_categoria = :id",
            ['nombre' => $nombre, 'id' => $id_categoria]
        );
    }

    public static function deleteCategoria($id)
    {
        return self::ExecuteStatement("DELETE FROM categorias WHERE id_categoria = :id", ['id' => $id]);
    }
}
