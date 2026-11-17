<?php
require_once __DIR__ . '/config/ConexionPDO.php';
require_once __DIR__ . '/models/Pedido.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = ConexionPDO::Conexion();
    $action = $_GET['action'] ?? '';

    if ($action === 'get_products') {
        $category = isset($_GET['cat']) ? filter_var($_GET['cat'], FILTER_VALIDATE_INT) : 0;
        $search = trim($_GET['busca'] ?? '');
        $sql = "SELECT p.id_producto AS id, p.nombre, p.descripcion, p.precio, p.imagen,
                       c.nombre AS categoria, c.id_categoria,
                       COALESCE(SUM(i.stock), 0) AS stock
                FROM productos p
                JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN inventario i ON i.id_producto = p.id_producto
                WHERE p.activo = 1";
        $params = [];
        if ($category > 0) {
            $sql .= " AND p.id_categoria = :category";
            $params['category'] = $category;
        }
        if ($search !== '') {
            $sql .= " AND (p.nombre LIKE :search_name OR p.descripcion LIKE :search_description)";
            $params['search_name'] = '%' . $search . '%';
            $params['search_description'] = '%' . $search . '%';
        }
        $sql .= " GROUP BY p.id_producto, p.nombre, p.descripcion, p.precio, p.imagen, c.nombre, c.id_categoria
                  HAVING COALESCE(SUM(i.stock), 0) > 0 ORDER BY c.nombre, p.nombre";
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        echo json_encode($statement->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get_variantes') {
        $idProducto = filter_var($_GET['id_producto'] ?? null, FILTER_VALIDATE_INT);
        if (!$idProducto || $idProducto < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'id_producto debe ser un entero positivo.']);
            exit;
        }
        $statement = $pdo->prepare(
            "SELECT id_inventario, id_producto, talla, stock FROM inventario
             WHERE id_producto = :id_producto AND stock > 0 ORDER BY talla"
        );
        $statement->execute(['id_producto' => $idProducto]);
        echo json_encode($statement->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get_categorias') {
        $statement = $pdo->query("SELECT id_categoria AS id, nombre FROM categorias ORDER BY nombre");
        echo json_encode($statement->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'crear_pedido') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['error' => 'El cuerpo debe contener un objeto JSON válido.']);
            exit;
        }
        $idUsuario = filter_var($body['id_usuario'] ?? $body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
        if (!$idUsuario || $idUsuario < 1 || empty($body['items']) || !is_array($body['items'])) {
            http_response_code(400);
            echo json_encode(['error' => 'id_usuario e items son obligatorios.']);
            exit;
        }
        $items = array_map(function ($item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Cada artículo debe ser un objeto de variante y cantidad.');
            }
            if (!isset($item['id_inventario']) && isset($item['id'])) {
                $item['id_inventario'] = $item['id'];
            }
            return $item;
        }, $body['items']);
        echo json_encode(
            ['success' => true] + Pedido::crearConItems($idUsuario, $items),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Acción no reconocida.']);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (DomainException $exception) {
    http_response_code(409);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
