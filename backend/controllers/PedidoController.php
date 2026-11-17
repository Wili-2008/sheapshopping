<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Pedido.php';

class PedidoController extends ApiController
{
    public function listarPedidos()
    {
        $this->run(function () {
            return Pedido::obtenerTodos();
        });
    }

    public function agregarPedido()
    {
        $this->run(function () {
            $data = $this->input();
            $idUsuario = $data['id_usuario'] ?? $data['id_cliente'] ?? null;
            $this->positiveInteger($idUsuario, 'id_usuario');
            if (isset($data['items'])) {
                if (!is_array($data['items'])) {
                    throw new InvalidArgumentException('items debe ser una lista de variantes y cantidades.');
                }
                return ['success' => true] + Pedido::crearConItems((int) $idUsuario, $data['items']);
            }

            $estado = $data['estado'] ?? 'pendiente';
            if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado'], true)) {
                throw new InvalidArgumentException('El estado del pedido no es válido.');
            }
            $total = $data['total'] ?? 0;
            if (!is_numeric($total) || (float) $total < 0) {
                throw new InvalidArgumentException('El total debe ser un número igual o mayor a cero.');
            }
            $id = Pedido::crear((int) $idUsuario, $data['fecha'] ?? null, $total, $estado);
            return ['success' => true, 'id_pedido' => $id];
        }, 201);
    }

    public function updatePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_pedido']);
            if (!isset($data['estado'])) {
                throw new InvalidArgumentException('El campo estado es obligatorio.');
            }
            return ['success' => true, 'affected' => Pedido::updatePedido(
                $this->positiveInteger($data['id_pedido'], 'id_pedido'),
                $data['estado']
            )];
        });
    }

    public function deletePedido()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Pedido::deletePedido(
                $this->positiveInteger($data['id_pedido'] ?? null, 'id_pedido')
            )];
        });
    }
}
