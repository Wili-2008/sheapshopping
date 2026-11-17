<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DetallePedido.php';

class DetallePedidoController extends ApiController
{
    public function listarDetallesPedidos()
    {
        $this->run(function () {
            $idPedido = isset($_GET['id_pedido'])
                ? $this->positiveInteger($_GET['id_pedido'], 'id_pedido')
                : null;
            return DetallePedido::obtenerTodos($idPedido);
        });
    }

    public function agregarDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_pedido', 'cantidad']);
            $idInventario = $data['id_inventario'] ?? null;
            if ($idInventario === null) {
                throw new InvalidArgumentException('Se requiere id_inventario (la talla/variante del producto).');
            }
            $cantidad = $this->positiveInteger($data['cantidad'], 'cantidad');
            return ['success' => true] + DetallePedido::crear(
                $this->positiveInteger($data['id_pedido'], 'id_pedido'),
                $this->positiveInteger($idInventario, 'id_inventario'),
                $cantidad
            );
        }, 201);
    }

    public function updateDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_detalle', 'id_inventario', 'cantidad']);
            return ['success' => true, 'affected' => DetallePedido::updateDetallePedido(
                $this->positiveInteger($data['id_detalle'], 'id_detalle'),
                $this->positiveInteger($data['id_inventario'], 'id_inventario'),
                $this->positiveInteger($data['cantidad'], 'cantidad')
            )];
        });
    }

    public function deleteDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => DetallePedido::deleteDetallePedido(
                $this->positiveInteger($data['id_detalle'] ?? null, 'id_detalle')
            )];
        });
    }
}
