<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Inventario extends ConexionPDO
{
    public static function obtenerTodos($id_producto = null)
    {
        $sql = "SELECT i.id_inventario, i.id_producto, p.nombre AS producto, i.talla, i.stock
                FROM inventario i JOIN productos p ON p.id_producto = i.id_producto";
        $params = [];
        if ($id_producto !== null) {
            $sql .= " WHERE i.id_producto = :id_producto";
            $params['id_producto'] = $id_producto;
        }
        $sql .= " ORDER BY p.nombre, i.talla";
        return self::ExecuteQuery($sql, $params);
    }

    public static function crear($id_producto, $talla, $stock)
    {
        return self::InsertarSql(
            "INSERT INTO inventario (id_producto, talla, stock) VALUES (:id_producto, :talla, :stock)",
            ['id_producto' => $id_producto, 'talla' => $talla, 'stock' => $stock]
        );
    }

    public static function actualizar($id_inventario, $talla, $stock)
    {
        return self::ExecuteStatement(
            "UPDATE inventario SET talla = :talla, stock = :stock WHERE id_inventario = :id",
            ['talla' => $talla, 'stock' => $stock, 'id' => $id_inventario]
        );
    }

    public static function eliminar($id_inventario)
    {
        return self::ExecuteStatement("DELETE FROM inventario WHERE id_inventario = :id", ['id' => $id_inventario]);
    }
}
