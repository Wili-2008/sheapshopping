<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class DetallePedido extends ConexionPDO
{
    public static function obtenerTodos($id_pedido = null)
    {
        $sql = "SELECT d.id_detalle, d.id_pedido, d.id_inventario, i.id_producto, p.nombre AS producto,
                       i.talla, d.cantidad, d.precio_unitario
                FROM detalle_pedidos d
                JOIN inventario i ON i.id_inventario = d.id_inventario
                JOIN productos p ON p.id_producto = i.id_producto";
        $params = [];
        if ($id_pedido !== null) {
            $sql .= " WHERE d.id_pedido = :id_pedido";
            $params['id_pedido'] = $id_pedido;
        }
        $sql .= " ORDER BY d.id_detalle";
        return self::ExecuteQuery($sql, $params);
    }

    public static function crear($id_pedido, $id_inventario, $cantidad)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $orderStatement = $pdo->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
            $orderStatement->execute(['id' => $id_pedido]);
            $order = $orderStatement->fetch();
            if (!$order || $order['estado'] !== 'pendiente') {
                throw new DomainException('Solo se pueden modificar los detalles de un pedido pendiente.');
            }

            $inventoryStatement = $pdo->prepare(
                "SELECT i.stock, p.precio FROM inventario i
                 JOIN productos p ON p.id_producto = i.id_producto
                 WHERE i.id_inventario = :id AND p.activo = 1 FOR UPDATE"
            );
            $inventoryStatement->execute(['id' => $id_inventario]);
            $inventory = $inventoryStatement->fetch();
            if (!$inventory || (int) $inventory['stock'] < $cantidad) {
                throw new DomainException('La variante solicitada no existe o no tiene stock suficiente.');
            }

            $decrease = $pdo->prepare(
                "UPDATE inventario SET stock = stock - :cantidad_set WHERE id_inventario = :id AND stock >= :cantidad_check"
            );
            $decrease->execute([
                'cantidad_set' => $cantidad,
                'cantidad_check' => $cantidad,
                'id' => $id_inventario,
            ]);
            $insert = $pdo->prepare(
                "INSERT INTO detalle_pedidos (id_pedido, id_inventario, cantidad, precio_unitario)
                 VALUES (:pedido, :inventario, :cantidad, :precio)"
            );
            $insert->execute([
                'pedido' => $id_pedido,
                'inventario' => $id_inventario,
                'cantidad' => $cantidad,
                'precio' => $inventory['precio'],
            ]);
            self::recalcularTotal($pdo, $id_pedido);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return ['id_detalle' => $id];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updateDetallePedido($id_detalle, $id_inventario, $cantidad)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $currentStatement = $pdo->prepare(
                "SELECT d.id_pedido, d.id_inventario, d.cantidad, p.estado
                 FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido = d.id_pedido
                 WHERE d.id_detalle = :id FOR UPDATE"
            );
            $currentStatement->execute(['id' => $id_detalle]);
            $current = $currentStatement->fetch();
            if (!$current || $current['estado'] !== 'pendiente') {
                throw new DomainException('Solo se pueden modificar detalles de pedidos pendientes.');
            }

            $restore = $pdo->prepare("UPDATE inventario SET stock = stock + :cantidad WHERE id_inventario = :id");
            $restore->execute(['cantidad' => $current['cantidad'], 'id' => $current['id_inventario']]);
            $inventoryStatement = $pdo->prepare(
                "SELECT i.stock, p.precio FROM inventario i
                 JOIN productos p ON p.id_producto = i.id_producto
                 WHERE i.id_inventario = :id AND p.activo = 1 FOR UPDATE"
            );
            $inventoryStatement->execute(['id' => $id_inventario]);
            $inventory = $inventoryStatement->fetch();
            if (!$inventory || (int) $inventory['stock'] < $cantidad) {
                throw new DomainException('La variante solicitada no existe o no tiene stock suficiente.');
            }

            $decrease = $pdo->prepare(
                "UPDATE inventario SET stock = stock - :cantidad_set WHERE id_inventario = :id AND stock >= :cantidad_check"
            );
            $decrease->execute([
                'cantidad_set' => $cantidad,
                'cantidad_check' => $cantidad,
                'id' => $id_inventario,
            ]);
            $update = $pdo->prepare(
                "UPDATE detalle_pedidos SET id_inventario = :inventario, cantidad = :cantidad,
                 precio_unitario = :precio WHERE id_detalle = :id"
            );
            $update->execute([
                'inventario' => $id_inventario,
                'cantidad' => $cantidad,
                'precio' => $inventory['precio'],
                'id' => $id_detalle,
            ]);
            self::recalcularTotal($pdo, $current['id_pedido']);
            $changed = $update->rowCount();
            $pdo->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function deleteDetallePedido($id_detalle)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "SELECT d.id_pedido, d.id_inventario, d.cantidad, p.estado
                 FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido = d.id_pedido
                 WHERE d.id_detalle = :id FOR UPDATE"
            );
            $statement->execute(['id' => $id_detalle]);
            $detail = $statement->fetch();
            if (!$detail) {
                $pdo->commit();
                return 0;
            }
            if ($detail['estado'] !== 'pendiente') {
                throw new DomainException('Solo se pueden eliminar detalles de pedidos pendientes.');
            }

            $restore = $pdo->prepare("UPDATE inventario SET stock = stock + :cantidad WHERE id_inventario = :id");
            $restore->execute(['cantidad' => $detail['cantidad'], 'id' => $detail['id_inventario']]);
            $delete = $pdo->prepare("DELETE FROM detalle_pedidos WHERE id_detalle = :id");
            $delete->execute(['id' => $id_detalle]);
            self::recalcularTotal($pdo, $detail['id_pedido']);
            $changed = $delete->rowCount();
            $pdo->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function recalcularTotal($pdo, $id_pedido)
    {
        $statement = $pdo->prepare(
            "UPDATE pedidos SET total = (
                SELECT COALESCE(SUM(cantidad * precio_unitario), 0)
                FROM detalle_pedidos WHERE id_pedido = :id_detalles
             ) WHERE id_pedido = :id_pedido"
        );
        $statement->execute(['id_detalles' => $id_pedido, 'id_pedido' => $id_pedido]);
    }
}
