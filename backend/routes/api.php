<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://quickorder',
    'http://quickorder.com',
    'https://quickorder.com',
    'http://localhost',
    'https://localhost',
    'http://127.0.0.1',
    'https://127.0.0.1',
];
if (
    $origin !== ''
    && (in_array($origin, $allowedOrigins, true)
        || preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin))
) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/ClienteController.php';
require_once __DIR__ . '/../controllers/CategoriaController.php';
require_once __DIR__ . '/../controllers/ProductoController.php';
require_once __DIR__ . '/../controllers/InventarioController.php';
require_once __DIR__ . '/../controllers/PedidoController.php';
require_once __DIR__ . '/../controllers/DetallePedidoController.php';
require_once __DIR__ . '/../controllers/ImagenController.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = trim((string) ($_GET['route'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
// El proyecto puede estar publicado dentro de una carpeta (por ejemplo,
// /sheap_shopping/backend/auth/login). Normaliza desde backend/ para que
// las rutas no dependan del nombre de la carpeta de publicación.
if (preg_match('#(?:^|/)backend/(.*)$#i', $uri, $backendPath)) {
    $uri = $backendPath[1];
}
foreach (['backend/', 'routes/api.php/', 'api.php/', 'api/'] as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        $uri = substr($uri, strlen($prefix));
    }
}
$uri = strtolower(trim($uri, '/'));

if (
    ($method === 'POST' || $method === 'PUT' || $method === 'DELETE')
    && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'El cuerpo debe contener un objeto JSON válido.']);
            exit;
        }
        $GLOBALS['_API_BODY'] = $decoded;
    }
}

$auth = new AuthController();
$usuario = new UsuarioController();
$cliente = new ClienteController();
$categoria = new CategoriaController();
$producto = new ProductoController();
$inventario = new InventarioController();
$pedido = new PedidoController();
$detalle = new DetallePedidoController();
$imagen = new ImagenController();

if ($method === 'GET') {
    switch ($uri) {
        case 'auth/verify':
            $auth->verifyToken();
            break;
        case 'listarusuarios':
        case 'listar_usuarios':
            $usuario->listarUsuarios();
            break;
        case 'listarcategorias':
        case 'listar_categorias':
            $categoria->listarCategorias();
            break;
        case 'listarproductos':
        case 'listar_productos':
        case 'productos':
            $producto->listarProductos();
            break;
        case 'listarclientes':
        case 'listar_clientes':
            $cliente->listarClientes();
            break;
        case 'listarinventario':
        case 'listar_inventario':
        case 'inventario':
            $inventario->listarInventario();
            break;
        case 'listarpedidos':
        case 'listar_pedidos':
            $pedido->listarPedidos();
            break;
        case 'listardetallepedidos':
        case 'listar_detallepedidos':
            $detalle->listarDetallesPedidos();
            break;
        default:
            if (preg_match('/^(?:borrar_usuario|delete_usuario)\/(\d+)$/', $uri, $matches)) {
                $_GET['id_usuario'] = $matches[1];
                $usuario->deleteUsuario();
                break;
            }
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ruta GET no encontrada.']);
    }
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? ($GLOBALS['_API_BODY']['action'] ?? '');
    if ($action === 'login') {
        $auth->login();
        exit;
    }
    $routes = [
        'subir_imagen' => [$imagen, 'subirImagen'],
        'auth/login-usuario' => [$auth, 'loginUsuario'],
        'auth/login-cliente' => [$auth, 'loginCliente'],
        'auth/registrar-cliente' => [$auth, 'registrarCliente'],
        'auth/logout' => [$auth, 'logout'],
        'agregar_usuario' => [$usuario, 'agregarUsuario'],
        'agregar_cliente' => [$cliente, 'agregarCliente'],
        'agregar_categoria' => [$categoria, 'agregarCategoria'],
        'agregar_producto' => [$producto, 'agregarProducto'],
        'agregar_inventario' => [$inventario, 'agregarInventario'],
        'agregar_pedido' => [$pedido, 'agregarPedido'],
        'agregar_detallepedido' => [$detalle, 'agregarDetallePedido'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'PUT') {
    $routes = [
        'update_usuario' => [$usuario, 'updateUsuario'],
        'update_cliente' => [$cliente, 'updateCliente'],
        'update_categoria' => [$categoria, 'updateCategoria'],
        'update_producto' => [$producto, 'updateProducto'],
        'update_inventario' => [$inventario, 'updateInventario'],
        'update_pedido' => [$pedido, 'updatePedido'],
        'update_detallepedido' => [$detalle, 'updateDetallePedido'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'DELETE') {
    $routes = [
        'delete_usuario' => [$usuario, 'deleteUsuario'],
        'delete_cliente' => [$cliente, 'deleteCliente'],
        'delete_categoria' => [$categoria, 'deleteCategoria'],
        'delete_producto' => [$producto, 'deleteProducto'],
        'delete_inventario' => [$inventario, 'deleteInventario'],
        'delete_pedido' => [$pedido, 'deletePedido'],
        'delete_detallepedido' => [$detalle, 'deleteDetallePedido'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} else {
    http_response_code(405);
    header('Allow: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Ruta no encontrada.']);
