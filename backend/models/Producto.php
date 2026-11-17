<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Producto extends ConexionPDO
{
    public static function obtenerTodos($incluirInactivos = true)
    {
        $where = $incluirInactivos ? '' : 'WHERE p.activo = 1';
        return self::ExecuteQuery(
            "SELECT p.id_producto, p.id_categoria, c.nombre AS categoria, p.nombre, p.descripcion,
                    p.precio, p.imagen, p.activo,
                    COALESCE(SUM(i.stock), 0) AS stock
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             LEFT JOIN inventario i ON i.id_producto = p.id_producto
             {$where}
             GROUP BY p.id_producto, p.id_categoria, c.nombre, p.nombre, p.descripcion, p.precio, p.imagen, p.activo
             ORDER BY p.id_producto"
        );
    }

    public static function crear($nombre, $descripcion, $precio, $imagen, $id_categoria, $activo = 1)
    {
        return self::InsertarSql(
            "INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, activo)
             VALUES (:id_categoria, :nombre, :descripcion, :precio, :imagen, :activo)",
            [
                'id_categoria' => $id_categoria,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'imagen' => $imagen,
                'activo' => $activo,
            ]
        );
    }

    public static function updateProducto($id, $nombre, $descripcion, $precio, $imagen, $id_categoria, $activo)
    {
        return self::ExecuteStatement(
            "UPDATE productos SET id_categoria = :id_categoria, nombre = :nombre, descripcion = :descripcion,
             precio = :precio, imagen = :imagen, activo = :activo WHERE id_producto = :id",
            [
                'id_categoria' => $id_categoria,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'imagen' => $imagen,
                'activo' => $activo,
                'id' => $id,
            ]
        );
    }

    public static function deleteProducto($id)
    {
        return self::ExecuteStatement("DELETE FROM productos WHERE id_producto = :id", ['id' => $id]);
    }
}
