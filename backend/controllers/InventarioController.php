<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Inventario.php';

class InventarioController extends ApiController
{
    public function listarInventario()
    {
        $this->run(function () {
            $idProducto = isset($_GET['id_producto'])
                ? $this->positiveInteger($_GET['id_producto'], 'id_producto')
                : null;
            return Inventario::obtenerTodos($idProducto);
        });
    }

    public function agregarInventario()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_producto', 'talla', 'stock']);
            $size = trim((string) $data['talla']);
            $stock = filter_var($data['stock'], FILTER_VALIDATE_INT);
            if ($size === '' || strlen($size) > 12 || $stock === false || $stock < 0) {
                throw new InvalidArgumentException('Talla o stock no válidos.');
            }
            return ['success' => true, 'id_inventario' => Inventario::crear(
                $this->positiveInteger($data['id_producto'], 'id_producto'),
                $size,
                $stock
            )];
        }, 201);
    }

    public function updateInventario()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_inventario', 'talla', 'stock']);
            $size = trim((string) $data['talla']);
            $stock = filter_var($data['stock'], FILTER_VALIDATE_INT);
            if ($size === '' || strlen($size) > 12 || $stock === false || $stock < 0) {
                throw new InvalidArgumentException('Talla o stock no válidos.');
            }
            return ['success' => true, 'affected' => Inventario::actualizar(
                $this->positiveInteger($data['id_inventario'], 'id_inventario'),
                $size,
                $stock
            )];
        });
    }

    public function deleteInventario()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Inventario::eliminar(
                $this->positiveInteger($data['id_inventario'] ?? null, 'id_inventario')
            )];
        });
    }
}
