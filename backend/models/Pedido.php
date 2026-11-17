<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Pedido extends ConexionPDO
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT p.id_pedido, p.id_usuario, u.nombre AS usuario, u.correo, p.fecha, p.estado, p.total
             FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_usuario
             ORDER BY p.fecha DESC, p.id_pedido DESC"
        );
    }

    public static function crear($id_usuario, $fecha = null, $total = 0, $estado = 'pendiente')
    {
        return self::InsertarSql(
            "INSERT INTO pedidos (id_usuario, fecha, estado, total)
             VALUES (:id_usuario, COALESCE(:fecha, CURRENT_TIMESTAMP), :estado, :total)",
            [
                'id_usuario' => $id_usuario,
                'fecha' => $fecha,
                'estado' => $estado,
                'total' => $total,
            ]
        );
    }

    public static function crearConItems($id_usuario, array $items)
    {
        if (!$items) {
            throw new InvalidArgumentException('El pedido debe incluir al menos un artículo.');
        }

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $total = 0.0;
            $lines = [];
            $quantities = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Cada artículo debe ser un objeto con id_inventario y cantidad.');
                }
                $idInventario = filter_var($item['id_inventario'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if (!$idInventario || !$cantidad || $cantidad < 1) {
                    throw new InvalidArgumentException('Cada artículo debe tener una variante y una cantidad válida.');
                }
                $quantities[$idInventario] = ($quantities[$idInventario] ?? 0) + $cantidad;
            }
            $inventoryQuery = $pdo->prepare(
                "SELECT i.id_inventario, i.stock, p.precio
                 FROM inventario i JOIN productos p ON p.id_producto = i.id_producto
                 WHERE i.id_inventario = :id AND p.activo = 1 FOR UPDATE"
            );
            $decreaseStock = $pdo->prepare(
                "UPDATE inventario SET stock = stock - :cantidad_set
                 WHERE id_inventario = :id AND stock >= :cantidad_check"
            );

            foreach ($quantities as $idInventario => $cantidad) {
                $inventoryQuery->execute(['id' => $idInventario]);
                $inventory = $inventoryQuery->fetch();
                if (!$inventory || (int) $inventory['stock'] < $cantidad) {
                    throw new DomainException('La variante solicitada no existe o no tiene stock suficiente.');
                }

                $price = (float) $inventory['precio'];
                $total += $price * $cantidad;
                $lines[] = [$idInventario, $cantidad, $price];
                $decreaseStock->execute([
                    'cantidad_set' => $cantidad,
                    'cantidad_check' => $cantidad,
                    'id' => $idInventario,
                ]);
                if ($decreaseStock->rowCount() !== 1) {
                    throw new DomainException('No hay stock suficiente para completar el pedido.');
                }
            }

            $insertOrder = $pdo->prepare(
                "INSERT INTO pedidos (id_usuario, estado, total) VALUES (:id_usuario, 'pendiente', :total)"
            );
            $insertOrder->execute(['id_usuario' => $id_usuario, 'total' => number_format($total, 2, '.', '')]);
            $idPedido = (int) $pdo->lastInsertId();
            $insertLine = $pdo->prepare(
                "INSERT INTO detalle_pedidos (id_pedido, id_inventario, cantidad, precio_unitario)
                 VALUES (:id_pedido, :id_inventario, :cantidad, :precio)"
            );
            foreach ($lines as $line) {
                $insertLine->execute([
                    'id_pedido' => $idPedido,
                    'id_inventario' => $line[0],
                    'cantidad' => $line[1],
                    'precio' => $line[2],
                ]);
            }

            $pdo->commit();
            return ['id_pedido' => $idPedido, 'total' => round($total, 2)];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updatePedido($id_pedido, $estado)
    {
        if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado'], true)) {
            throw new InvalidArgumentException('El estado del pedido no es válido.');
        }

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
            $statement->execute(['id' => $id_pedido]);
            $order = $statement->fetch();
            if (!$order || $order['estado'] === 'rechazado' || $order['estado'] === $estado) {
                $pdo->commit();
                return 0;
            }
            if ($order['estado'] === 'aprobado' && $estado === 'pendiente') {
                throw new DomainException('No se puede reabrir un pedido aprobado.');
            }

            if ($estado === 'rechazado' && $order['estado'] !== 'rechazado') {
                $restore = $pdo->prepare(
                    "UPDATE inventario i JOIN detalle_pedidos d ON d.id_inventario = i.id_inventario
                     SET i.stock = i.stock + d.cantidad WHERE d.id_pedido = :id"
                );
                $restore->execute(['id' => $id_pedido]);
            }
            $update = $pdo->prepare("UPDATE pedidos SET estado = :estado WHERE id_pedido = :id");
            $update->execute(['estado' => $estado, 'id' => $id_pedido]);
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

    public static function deletePedido($id_pedido)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $order = $pdo->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
            $order->execute(['id' => $id_pedido]);
            $record = $order->fetch();
            if (!$record) {
                $pdo->commit();
                return 0;
            }
            if ($record['estado'] !== 'rechazado') {
                $restore = $pdo->prepare(
                    "UPDATE inventario i JOIN detalle_pedidos d ON d.id_inventario = i.id_inventario
                     SET i.stock = i.stock + d.cantidad WHERE d.id_pedido = :id"
                );
                $restore->execute(['id' => $id_pedido]);
            }
            $deleteDetails = $pdo->prepare("DELETE FROM detalle_pedidos WHERE id_pedido = :id");
            $deleteDetails->execute(['id' => $id_pedido]);
            $deleteOrder = $pdo->prepare("DELETE FROM pedidos WHERE id_pedido = :id");
            $deleteOrder->execute(['id' => $id_pedido]);
            $deleted = $deleteOrder->rowCount();
            $pdo->commit();
            return $deleted;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
