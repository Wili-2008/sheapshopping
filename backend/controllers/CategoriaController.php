<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Categoria.php';

class CategoriaController extends ApiController
{
    public function listarCategorias()
    {
        $this->run(function () {
            return Categoria::obtenerTodos();
        });
    }

    public function agregarCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre']);
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 80) {
                throw new InvalidArgumentException('El nombre es obligatorio y no puede superar 80 caracteres.');
            }
            return ['success' => true, 'id_categoria' => Categoria::crear($nombre)];
        }, 201);
    }

    public function updateCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_categoria', 'nombre']);
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 80) {
                throw new InvalidArgumentException('El nombre es obligatorio y no puede superar 80 caracteres.');
            }
            return ['success' => true, 'affected' => Categoria::updateCategoria(
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $nombre
            )];
        });
    }

    public function deleteCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Categoria::deleteCategoria(
                $this->positiveInteger($data['id_categoria'] ?? null, 'id_categoria')
            )];
        });
    }
}
