<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Producto.php';

class ProductoController extends ApiController
{
    public function listarProductos()
    {
        $this->run(function () {
            return Producto::obtenerTodos();
        });
    }

    public function agregarProducto()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre', 'precio', 'id_categoria']);
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            if ($name === '' || strlen($name) > 120 || !is_numeric($price) || (float) $price < 0) {
                throw new InvalidArgumentException('Nombre y precio válido son obligatorios.');
            }
            $id = Producto::crear(
                $name,
                $data['descripcion'] ?? null,
                $price,
                $data['imagen'] ?? null,
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $this->activeFlag($data['activo'] ?? 1)
            );
            return ['success' => true, 'id_producto' => $id];
        }, 201);
    }

    public function updateProducto()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_producto', 'nombre', 'precio', 'id_categoria']);
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            if ($name === '' || strlen($name) > 120 || !is_numeric($price) || (float) $price < 0) {
                throw new InvalidArgumentException('Nombre y precio válido son obligatorios.');
            }
            return ['success' => true, 'affected' => Producto::updateProducto(
                $this->positiveInteger($data['id_producto'], 'id_producto'),
                $name,
                $data['descripcion'] ?? null,
                $price,
                $data['imagen'] ?? null,
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $this->activeFlag($data['activo'] ?? 1)
            )];
        });
    }

    public function deleteProducto()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Producto::deleteProducto(
                $this->positiveInteger($data['id_producto'] ?? null, 'id_producto')
            )];
        });
    }

    private function activeFlag($value)
    {
        if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
            throw new InvalidArgumentException('El campo activo debe ser booleano.');
        }
        return (int) (bool) $value;
    }
}
