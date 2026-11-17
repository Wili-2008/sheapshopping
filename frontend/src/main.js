import { api } from './utils/api.js';
import { clearSession, getSession, saveSession } from './utils/session.js';

const root = document.querySelector('#app');
const app = { user: getSession(), view: 'catalog', category: 'Todo', query: '', products: [], categories: [], inventory: [], orders: [], details: [], users: [], cart: [], message: '', busy: false };
const money = (value) => `Bs ${Number(value || 0).toFixed(2)}`;
const esc = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
const roleName = (role) => role === 'admin' ? 'Administrador' : 'Cliente';
const imagePath = (path) => {
    if (!path) return '';
    if (/^(https?:|data:|\/)/i.test(path)) return path;
    const filename = path.replace(/^\.\//, '').replace(/^imagenes\//, '');
    const url = new URL('../../api.php', import.meta.url);
    url.searchParams.set('imagen', filename);
    return url.href;
};

async function loadStore() {
    const [products, categories] = await Promise.all([api.get('listarproductos'), api.get('listarcategorias')]);
    app.products = Array.isArray(products) ? products : [];
    app.categories = Array.isArray(categories) ? categories : [];
    app.inventory = await api.get('listarinventario').catch(() => []);
}

async function loadOrders() {
    app.orders = await api.get('listarpedidos');
    app.orders = Array.isArray(app.orders) ? app.orders : [];
    app.details = await api.get('listardetallepedidos').catch(() => []);
    app.details = Array.isArray(app.details) ? app.details : [];
}

async function loadUsers() {
    const users = await api.get('listarusuarios');
    app.users = Array.isArray(users) ? users : [];
}

function setMessage(message, kind = 'success') {
    app.message = message ? { text: message, kind } : null;
}

function authScreen(mode = 'login') {
    root.innerHTML = `<main class="auth-page"><section class="auth-showcase"><a class="brand brand-light" href="#"><span class="brand-mark">S</span><span>SHEAP<br><b>SHOPPING</b></span></a><div class="showcase-copy"><p class="eyebrow">ESTILO PARA CADA DÍA</p><h1>Viste a tu manera.</h1><p>Encuentra tus próximos favoritos en un solo lugar.</p></div><div class="showcase-stamp">NUEVA<br>COLECCIÓN<br><b>2026</b></div></section><section class="auth-panel"><div class="auth-card"><p class="eyebrow">SHEAP SHOPPING</p><h2>${mode === 'login' ? 'Qué gusto verte' : 'Crea tu cuenta'}</h2><p class="muted">${mode === 'login' ? 'Ingresa para descubrir la colección.' : 'Regístrate gratis como cliente y empieza a explorar.'}</p>${app.message ? `<div class="notice ${app.message.kind}">${esc(app.message.text)}</div>` : ''}<form id="authForm" class="form-stack">${mode === 'register' ? '<label>Nombre completo<input name="nombre" required maxlength="100" autocomplete="name" placeholder="Tu nombre"></label>' : ''}<label>Correo electrónico<input name="correo" type="email" required autocomplete="email" placeholder="nombre@correo.com"></label><label>Contraseña<input name="contrasena" type="password" required minlength="6" autocomplete="${mode === 'login' ? 'current-password' : 'new-password'}" placeholder="Mínimo 6 caracteres"></label><button class="button button-dark full" type="submit">${mode === 'login' ? 'Iniciar sesión' : 'Crear cuenta'} <span>→</span></button></form><p class="auth-switch">${mode === 'login' ? '¿Aún no tienes cuenta?' : '¿Ya tienes una cuenta?'} <button class="link-button" data-auth-mode="${mode === 'login' ? 'register' : 'login'}">${mode === 'login' ? 'Regístrate' : 'Inicia sesión'}</button></p><p class="auth-footnote">Al continuar, aceptas el uso académico de esta tienda de demostración.</p></div></section></main>`;
    root.querySelector('#authForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        const button = event.currentTarget.querySelector('button[type=submit]');
        button.disabled = true; button.textContent = 'Un momento…'; app.message = null;
        try {
            const response = mode === 'login' ? await api.post('auth/login', { action: 'login', correo: data.correo, contrasena: data.contrasena }) : await api.post('auth/registrar-cliente', data);
            app.user = saveSession(response.user, response.token);
            app.cart = [];
            await enterStore();
        } catch (error) { app.message = { text: error.message || 'No se pudo completar la solicitud.', kind: 'error' }; authScreen(mode); }
    });
    root.querySelector('[data-auth-mode]').addEventListener('click', () => { app.message = null; authScreen(mode === 'login' ? 'register' : 'login'); });
}

function enterStore() {
    if (!app.user || !['admin', 'cliente'].includes(app.user.rol)) { clearSession(); app.user = null; authScreen(); return; }
    return loadStore().then(() => { app.view = 'catalog'; render(); }).catch((error) => { app.message = { text: `No se pudieron cargar los datos: ${error.message}`, kind: 'error' }; render(); });
}

function navLink(view, label, badge = '') { return `<button class="nav-item ${app.view === view ? 'active' : ''}" data-view="${view}">${label}${badge}</button>`; }
function shell(content) {
    const admin = app.user.rol === 'admin';
    const count = app.cart.reduce((sum, item) => sum + item.quantity, 0);
    return `<div class="shop-shell"><header class="topbar"><a class="brand" href="#" data-view="catalog"><span class="brand-mark">S</span><span>SHEAP<br><b>SHOPPING</b></span></a><nav class="main-nav" aria-label="Navegación principal">${navLink('catalog','Catálogo')}${navLink('cart',`Carrito <span class="nav-count">${count}</span>`)}${navLink('orders','Mis pedidos')}${admin ? navLink('admin','Administración') : ''}</nav><div class="user-menu"><span class="user-avatar">${esc((app.user.nombre || app.user.correo || 'S').slice(0,1).toUpperCase())}</span><span class="user-greeting"><b>${esc(app.user.nombre || 'Cuenta')}</b><small>${roleName(app.user.rol)}</small></span><button class="logout-button" data-action="logout" title="Cerrar sesión" aria-label="Cerrar sesión">↗</button></div><button class="mobile-menu-button" data-action="menu" aria-label="Abrir menú">☰</button></header><div id="mobileNav" class="mobile-nav">${navLink('catalog','Catálogo')}${navLink('cart',`Carrito (${count})`)}${navLink('orders','Mis pedidos')}${admin ? navLink('admin','Administración') : ''}<button class="nav-item" data-action="logout">Cerrar sesión</button></div>${app.message ? `<div class="toast ${app.message.kind}" role="status">${esc(app.message.text)}<button data-action="dismiss" aria-label="Cerrar">×</button></div>` : ''}<main class="page-content">${content}</main><footer class="site-footer"><span>SHEAP SHOPPING</span><span>Moda para todos los días · Bolivia</span><span>Proyecto académico 2026</span></footer></div>`;
}

function productCategory(product) {
    const name = `${product.nombre || ''} ${product.descripcion || ''}`.toLowerCase();
    const category = String(product.categoria || '').toLowerCase();
    if (name.includes('polera') || name.includes('camiseta') || category.includes('polera')) return 'Poleras';
    if (name.includes('abrigo') || name.includes('chamarra') || name.includes('sudadera') || category.includes('abrigo')) return 'Abrigos';
    if (name.includes('calzado') || name.includes('zapatilla') || name.includes('zapato') || category.includes('calzado')) return 'Calzado';
    if (name.includes('short') || name.includes('bermuda')) return 'Shorts';
    if (name.includes('pantal') || name.includes('jean')) return 'Pantalones';
    return category.includes('pantalones y shorts') ? 'Pantalones' : product.categoria || 'Otros';
}

function productCard(product) {
    const variants = app.inventory.filter((item) => Number(item.id_producto) === Number(product.id_producto));
    const available = variants.filter((item) => Number(item.stock) > 0);
    const img = imagePath(product.imagen);
    return `<article class="product-card"><div class="product-photo">${img ? `<img src="${esc(img)}" alt="${esc(product.nombre)}" loading="lazy" onerror="this.remove()">` : ''}<span class="product-index">${esc(productCategory(product))}</span>${Number(product.stock) === 0 ? '<span class="sold-out">Agotado</span>' : ''}<button class="quick-add" data-action="quick" data-product="${product.id_producto}" aria-label="Ver tallas de ${esc(product.nombre)}">+</button></div><div class="product-info"><div class="product-heading"><div><p class="product-category">${esc(productCategory(product))}</p><h3>${esc(product.nombre)}</h3></div><strong>${money(product.precio)}</strong></div><p class="product-description">${esc(product.descripcion || 'Una prenda versátil para completar tu estilo.')}</p><div class="product-buy"><label class="select-wrap"><span class="sr-only">Talla</span><select data-size="${product.id_producto}" ${available.length ? '' : 'disabled'}>${available.length ? available.map((variant) => `<option value="${variant.id_inventario}">${esc(variant.talla)} · ${variant.stock} disp.</option>`).join('') : '<option>Sin stock</option>'}</select></label><label class="qty-wrap"><span class="sr-only">Cantidad</span><input type="number" min="1" max="${available[0]?.stock || 1}" value="1" data-qty="${product.id_producto}" ${available.length ? '' : 'disabled'}></label></div><button class="button button-dark full" data-action="add" data-product="${product.id_producto}" ${available.length ? '' : 'disabled'}>${available.length ? 'Añadir al carrito' : 'Agotado'} <span>↗</span></button></div></article>`;
}

function catalogPage() {
    const categories = ['Todo', 'Poleras', 'Abrigos', 'Calzado', 'Pantalones', 'Shorts'];
    const products = app.products.filter((product) => Number(product.activo) === 1 && (app.category === 'Todo' || productCategory(product) === app.category) && `${product.nombre} ${product.descripcion || ''}`.toLowerCase().includes(app.query.toLowerCase()));
    return `<section class="hero"><div class="hero-copy"><p class="eyebrow">COLECCIÓN · PRIMAVERA / VERANO</p><h1>Tu próximo<br><em>favorito</em> está aquí.</h1><p>Prendas con estilo, comodidad y personalidad. Encuentra ese look que habla de ti.</p><button class="button button-cream" data-action="browse">Explorar colección <span>↓</span></button></div><div class="hero-art"><div class="hero-shape shape-one"></div><div class="hero-shape shape-two"></div><div class="hero-number">01<span>/</span>04</div><div class="hero-caption">NUEVA TEMPORADA<br><b>VÍSTETE COMO ERES</b></div></div><div class="hero-side-label">SHEAP · BOLIVIA · 2026</div></section><section class="catalog-section" id="collection"><div class="section-head"><div><p class="eyebrow">ENCUENTRA TU ESTILO</p><h2>La colección</h2></div><span class="item-count">${products.length} productos</span></div><div class="catalog-controls"><div class="category-tabs">${categories.map((category) => `<button class="category-tab ${app.category === category ? 'selected' : ''}" data-category="${category}">${category}</button>`).join('')}</div><label class="search-box"><span>⌕</span><input type="search" placeholder="Buscar una prenda…" value="${esc(app.query)}" data-search></label></div><div class="product-grid">${products.length ? products.map(productCard).join('') : '<div class="empty-state"><span>✳</span><h3>No encontramos productos</h3><p>Prueba otra categoría o palabra de búsqueda.</p></div>'}</div></section><section class="promise-row"><div><span>01</span><b>Compra con calma</b><small>Revisa tu pedido antes de confirmar.</small></div><div><span>02</span><b>Tu talla, tu estilo</b><small>Disponibilidad actualizada por variante.</small></div><div><span>03</span><b>Seguimiento fácil</b><small>Consulta el estado de tus pedidos.</small></div></section>`;
}

function cartPage() {
    const total = app.cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    return `<section class="page-intro"><p class="eyebrow">TU SELECCIÓN</p><h1>Tu carrito<span class="title-dot">.</span></h1><p>Revisa tallas, cantidades y el precio de cada prenda.</p></section>${app.cart.length ? `<div class="cart-layout"><section class="cart-list">${app.cart.map((item, index) => `<article class="cart-row"><div class="cart-thumb">${item.image ? `<img src="${esc(item.image)}" alt="">` : '<span>✳</span>'}</div><div class="cart-product"><small>${esc(item.category)}</small><h3>${esc(item.name)}</h3><p>Talla ${esc(item.size)}</p></div><div class="cart-quantity"><button data-action="cart-dec" data-index="${index}" aria-label="Disminuir cantidad">−</button><span>${item.quantity}</span><button data-action="cart-inc" data-index="${index}" aria-label="Aumentar cantidad">+</button></div><div class="cart-price"><small>Precio unitario</small><b>${money(item.price)}</b><small>Subtotal: ${money(item.price * item.quantity)}</small></div><button class="remove-item" data-action="remove" data-index="${index}" aria-label="Eliminar artículo">×</button></article>`).join('')}<button class="text-action" data-action="clear-cart">Vaciar carrito</button></section><section class="order-summary"><p class="eyebrow">RESUMEN</p><h2>Tu pedido</h2><div class="summary-line"><span>Artículos (${app.cart.reduce((s,i)=>s+i.quantity,0)})</span><b>${money(total)}</b></div><div class="summary-line"><span>Envío</span><span>Coordinar al retirar</span></div><div class="summary-total"><span>Total</span><b>${money(total)}</b></div><p class="summary-note">El pedido se registrará como pendiente. El precio y el stock se verifican al confirmar.</p><button class="button button-dark full" data-action="confirm-order">Confirmar pedido <span>↗</span></button><button class="button button-outline full" data-view="catalog">Seguir comprando</button></section></div>` : `<div class="empty-cart"><div class="empty-bag">S</div><p class="eyebrow">TODAVÍA NO HAY PRENDAS</p><h2>Tu carrito está esperando.</h2><p>Explora el catálogo y guarda aquí tus favoritos.</p><button class="button button-dark" data-view="catalog">Ir al catálogo <span>↗</span></button></div>`}`;
}

function orderStatus(status) { const value = String(status || 'pendiente').toLowerCase(); return `<span class="status-pill status-${esc(value)}">${value === 'aprobado' ? 'Aprobado' : value === 'rechazado' ? 'Rechazado' : 'Pendiente'}</span>`; }
function dateLabel(value) { const date = new Date(String(value || '').replace(' ', 'T')); return Number.isNaN(date.valueOf()) ? '—' : date.toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' }); }
function orderCard(order) {
    const items = app.details.filter((detail) => Number(detail.id_pedido) === Number(order.id_pedido));
    return `<article class="order-card"><div class="order-top"><div><small>PEDIDO #${esc(order.id_pedido)}</small><h3>${dateLabel(order.fecha)}</h3></div>${orderStatus(order.estado)}</div><div class="order-items">${items.length ? items.map((item) => `<div><span>${esc(item.producto)} <small>· Talla ${esc(item.talla)} × ${item.cantidad}</small></span><b>${money(Number(item.precio_unitario) * Number(item.cantidad))}</b></div>`).join('') : '<p>Detalle del pedido no disponible.</p>'}</div><div class="order-bottom"><span>${items.reduce((n, item) => n + Number(item.cantidad), 0)} artículos</span><strong>${money(order.total)}</strong></div></article>`;
}
function ordersPage() {
    const orders = app.user.rol === 'admin' ? app.orders : app.orders.filter((order) => Number(order.id_usuario) === Number(app.user.id));
    return `<section class="page-intro"><p class="eyebrow">${app.user.rol === 'admin' ? 'SEGUIMIENTO DE VENTAS' : 'TU ACTIVIDAD'}</p><h1>${app.user.rol === 'admin' ? 'Pedidos' : 'Mis pedidos'}<span class="title-dot">.</span></h1><p>${app.user.rol === 'admin' ? 'Revisa las compras y actualiza su estado.' : 'Consulta el estado de cada compra que realizaste.'}</p></section><div class="orders-toolbar"><div class="order-stats"><b>${orders.length}</b><span>pedidos registrados</span></div><button class="button button-outline" data-action="refresh-orders">Actualizar ↻</button></div><div class="orders-grid">${orders.length ? orders.map(orderCard).join('') : '<div class="empty-state"><span>✳</span><h3>Aún no hay pedidos</h3><p>Cuando realices una compra, aparecerá aquí.</p></div>'}</div>`;
}

function adminPage() {
    const tab = app.adminTab || 'products';
    const nav = [['products','Productos'],['inventory','Inventario'],['orders','Pedidos'],['sales','Ventas'],['users','Usuarios']];
    let panel = '';
    if (tab === 'products') panel = adminProducts();
    if (tab === 'inventory') panel = adminInventory();
    if (tab === 'orders') panel = adminOrders();
    if (tab === 'sales') panel = adminSales();
    if (tab === 'users') panel = adminUsers();
    return `<section class="page-intro admin-intro"><p class="eyebrow">ESPACIO DE GESTIÓN</p><h1>Administración<span class="title-dot">.</span></h1><p>Productos, inventario y pedidos de SHEAP SHOPPING.</p></section><div class="admin-tabs">${nav.map(([key, label]) => `<button class="admin-tab ${tab === key ? 'selected' : ''}" data-admin-tab="${key}">${label}</button>`).join('')}</div><section class="admin-panel">${panel}</section>`;
}
function adminProducts() {
    const editing = app.editingProduct || null;
    const currentImage = editing?.imagen ? imagePath(editing.imagen) : '';
    const form = `<form id="productForm" class="admin-form"><input type="hidden" name="id_producto" value="${editing?.id_producto || ''}"><input type="hidden" name="imagen" value="${esc(editing?.imagen || '')}"><h3>${editing ? 'Editar producto' : 'Agregar producto'}</h3><div class="form-grid"><label>Nombre<input name="nombre" required maxlength="120" value="${esc(editing?.nombre || '')}"></label><label>Categoría<select name="id_categoria" required>${app.categories.map((c) => `<option value="${c.id_categoria}" ${Number(editing?.id_categoria) === Number(c.id_categoria) ? 'selected' : ''}>${esc(c.nombre)}</option>`).join('')}</select></label><label>Precio (Bs)<input name="precio" type="number" step="0.01" min="0" required value="${editing?.precio || ''}"></label><div class="image-upload-field"><label>Fotografía del producto<input name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-product-image><small>JPG, PNG o WEBP · máximo 5 MB</small></label><div class="image-preview" data-image-preview>${currentImage ? `<img src="${esc(currentImage)}" alt="Vista previa del producto">` : '<span>La foto aparecerá aquí</span>'}</div></div><label class="wide">Descripción<textarea name="descripcion" rows="2">${esc(editing?.descripcion || '')}</textarea></label></div><div class="form-actions"><label class="check-label"><input type="checkbox" name="activo" ${Number(editing?.activo ?? 1) === 1 ? 'checked' : ''}> Visible en catálogo</label><button class="button button-dark" type="submit">${editing ? 'Guardar cambios' : 'Crear producto'} ↗</button>${editing ? '<button class="button button-outline" type="button" data-action="cancel-edit">Cancelar</button>' : ''}</div></form>`;
    return `<div class="panel-heading"><div><p class="eyebrow">CATÁLOGO</p><h2>Productos</h2></div><span class="item-count">${app.products.length} productos</span></div>${form}<div class="table-wrap"><table><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Visibilidad</th><th></th></tr></thead><tbody>${app.products.map((p) => `<tr><td><b>${esc(p.nombre)}</b><small>${esc(p.descripcion || '')}</small></td><td>${esc(p.categoria || '')}</td><td>${money(p.precio)}</td><td>${p.stock}</td><td><span class="status-pill ${Number(p.activo) ? 'status-aprobado' : 'status-rechazado'}">${Number(p.activo) ? 'Visible' : 'Oculto'}</span></td><td class="row-actions"><button data-action="edit-product" data-id="${p.id_producto}">Editar</button><button data-action="toggle-product" data-id="${p.id_producto}">${Number(p.activo) ? 'Ocultar' : 'Mostrar'}</button></td></tr>`).join('') || '<tr><td colspan="6" class="table-empty">No hay productos registrados.</td></tr>'}</tbody></table></div>`;
}
function adminInventory() {
    const products = app.products.filter((p) => Number(p.activo) === 1);
    return `<div class="panel-heading"><div><p class="eyebrow">EXISTENCIAS POR TALLA</p><h2>Inventario</h2></div><span class="item-count">${app.inventory.length} variantes</span></div><form id="variantForm" class="inline-form"><label>Producto<select name="id_producto" required>${products.map((p) => `<option value="${p.id_producto}">${esc(p.nombre)}</option>`).join('')}</select></label><label>Talla<input name="talla" required maxlength="12" placeholder="M / 38"></label><label>Stock inicial<input name="stock" type="number" min="0" required value="0"></label><button class="button button-dark" type="submit">Añadir talla +</button></form><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Talla</th><th>Disponibles</th><th>Actualizar stock</th></tr></thead><tbody>${app.inventory.map((row) => `<tr><td><b>${esc(row.producto)}</b></td><td>${esc(row.talla)}</td><td>${Number(row.stock) ? `<span class="stock-value">${row.stock} unidades</span>` : '<span class="stock-empty">Agotado</span>'}</td><td><form class="stock-form" data-stock-form="${row.id_inventario}"><input name="stock" type="number" min="0" value="${row.stock}" aria-label="Nuevo stock"><button class="text-action" type="submit">Guardar</button></form></td></tr>`).join('') || '<tr><td colspan="4" class="table-empty">Todavía no hay variantes de inventario.</td></tr>'}</tbody></table></div>`;
}
function adminOrders() {
    return `<div class="panel-heading"><div><p class="eyebrow">GESTIONAR ESTADO</p><h2>Pedidos de clientes</h2></div><button class="button button-outline" data-action="refresh-orders">Actualizar ↻</button></div><div class="admin-orders-list">${app.orders.length ? app.orders.map((order) => `<article class="admin-order">${orderCard(order)}<div class="order-controls"><label>Estado<select data-order-status="${order.id_pedido}" ${order.estado === 'rechazado' ? 'disabled' : ''}><option value="pendiente" ${order.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option><option value="aprobado" ${order.estado === 'aprobado' ? 'selected' : ''}>Aprobado</option><option value="rechazado" ${order.estado === 'rechazado' ? 'selected' : ''}>Rechazado</option></select></label><button class="button button-dark" data-action="save-status" data-id="${order.id_pedido}" ${order.estado === 'rechazado' ? 'disabled' : ''}>Actualizar estado</button></div></article>`).join('') : '<div class="empty-state"><h3>No hay pedidos registrados</h3></div>'}</div>`;
}
function adminSales() {
    const approved = app.orders.filter((order) => order.estado === 'aprobado');
    const now = new Date(); const weekStart = new Date(now); weekStart.setDate(now.getDate() - ((now.getDay() + 6) % 7)); weekStart.setHours(0,0,0,0); const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const sumSince = (start) => approved.filter((order) => new Date(String(order.fecha).replace(' ', 'T')) >= start).reduce((sum, order) => sum + Number(order.total), 0);
    return `<div class="panel-heading"><div><p class="eyebrow">SOLO PEDIDOS APROBADOS</p><h2>Ventas e ingresos</h2></div></div><div class="sales-cards"><article><small>VENTAS TOTALES</small><b>${approved.length}</b><span>pedidos aprobados</span></article><article><small>INGRESOS ACUMULADOS</small><b>${money(approved.reduce((sum, order) => sum + Number(order.total), 0))}</b><span>todos los tiempos</span></article><article><small>ESTA SEMANA</small><b>${money(sumSince(weekStart))}</b><span>desde el lunes</span></article><article><small>ESTE MES</small><b>${money(sumSince(monthStart))}</b><span>${now.toLocaleDateString('es-BO',{month:'long'})}</span></article></div><div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Importe</th></tr></thead><tbody>${approved.map((o) => `<tr><td>#${o.id_pedido}</td><td>${dateLabel(o.fecha)}</td><td>${esc(o.usuario || 'Cliente')}</td><td><b>${money(o.total)}</b></td></tr>`).join('') || '<tr><td colspan="4" class="table-empty">Todavía no hay ventas aprobadas.</td></tr>'}</tbody></table></div>`;
}
function adminUsers() {
    return `<div class="panel-heading"><div><p class="eyebrow">ACCESO A LA TIENDA</p><h2>Usuarios</h2></div><span class="item-count">${app.users.length} cuentas</span></div><form id="userForm" class="inline-form"><label>Nombre<input name="nombre" required maxlength="100"></label><label>Correo<input name="correo" type="email" required></label><label>Contraseña<input name="contrasena" type="password" required minlength="6"></label><label>Rol<select name="rol"><option value="cliente">Cliente</option><option value="admin">Administrador</option></select></label><button class="button button-dark" type="submit">Crear cuenta +</button></form><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Registro</th></tr></thead><tbody>${app.users.map((user) => `<tr><td><b>${esc(user.nombre)}</b></td><td>${esc(user.correo)}</td><td>${roleName(user.rol)}</td><td>${dateLabel(user.fecha_registro)}</td></tr>`).join('') || '<tr><td colspan="4" class="table-empty">No hay usuarios registrados.</td></tr>'}</tbody></table></div>`;
}

function render() {
    if (!app.user) { authScreen(); return; }
    if (app.view === 'admin' && app.user.rol !== 'admin') app.view = 'catalog';
    let content = app.view === 'cart' ? cartPage() : app.view === 'orders' ? ordersPage() : app.view === 'admin' ? adminPage() : catalogPage();
    root.innerHTML = shell(content);
    root.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', async (event) => {
        const view = event.currentTarget.dataset.view;
        if (!['catalog','cart','orders','admin'].includes(view)) return;
        if (view === 'orders' || view === 'admin') { try { await loadOrders(); } catch (error) { setMessage(error.message, 'error'); } }
        if (view === 'admin' && app.user.rol === 'admin') { app.adminTab ||= 'products'; try { await loadStore(); if (app.adminTab === 'users') await loadUsers(); } catch (error) { setMessage(error.message, 'error'); } }
        app.view = view; app.message = null; render(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
    root.querySelectorAll('[data-category]').forEach((button) => button.addEventListener('click', () => { app.category = button.dataset.category; render(); }));
    root.querySelector('[data-search]')?.addEventListener('input', (event) => { const cursor = event.target.selectionStart; app.query = event.target.value; const y = window.scrollY; render(); const input = root.querySelector('[data-search]'); input.focus(); input.setSelectionRange(cursor, cursor); window.scrollTo(0,y); });
    root.querySelectorAll('[data-admin-tab]').forEach((button) => button.addEventListener('click', async () => { app.adminTab = button.dataset.adminTab; app.message = null; if (app.adminTab === 'users') await loadUsers().catch((e) => setMessage(e.message,'error')); if (app.adminTab === 'orders' || app.adminTab === 'sales') await loadOrders().catch((e) => setMessage(e.message,'error')); await loadStore().catch(() => {}); render(); }));
    bindActions();
}

function bindActions() {
    root.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', async (event) => {
        const el = event.currentTarget; const action = el.dataset.action;
        try {
            if (action === 'logout') { await api.post('auth/logout', {}).catch(() => {}); clearSession(); app.user = null; app.message = null; authScreen(); }
            if (action === 'dismiss') { app.message = null; render(); }
            if (action === 'browse') document.querySelector('#collection')?.scrollIntoView({ behavior: 'smooth' });
            if (action === 'menu') root.querySelector('#mobileNav').classList.toggle('open');
            if (action === 'add' || action === 'quick') {
                if (action === 'quick') { document.querySelector(`[data-size="${el.dataset.product}"]`)?.focus(); return; }
                const id = el.dataset.product; const product = app.products.find((p) => Number(p.id_producto) === Number(id)); const variantId = root.querySelector(`[data-size="${id}"]`)?.value; const variant = app.inventory.find((v) => Number(v.id_inventario) === Number(variantId)); const quantity = Math.max(1, Number(root.querySelector(`[data-qty="${id}"]`)?.value || 1));
                if (!product || !variant || quantity > Number(variant.stock)) throw new Error('La cantidad supera el stock disponible.');
                const existing = app.cart.find((item) => Number(item.variantId) === Number(variant.id_inventario));
                if (existing) { if (existing.quantity + quantity > Number(variant.stock)) throw new Error('No hay stock suficiente para esa cantidad.'); existing.quantity += quantity; }
                else app.cart.push({ variantId: variant.id_inventario, productId: product.id_producto, name: product.nombre, category: productCategory(product), size: variant.talla, stock: Number(variant.stock), quantity, price: Number(product.precio), image: imagePath(product.imagen) });
                setMessage(`${product.nombre} · talla ${variant.talla} añadido al carrito.`); render();
            }
            if (action === 'cart-inc' || action === 'cart-dec') { const item = app.cart[Number(el.dataset.index)]; if (action === 'cart-inc' && item.quantity < item.stock) item.quantity += 1; if (action === 'cart-dec' && item.quantity > 1) item.quantity -= 1; render(); }
            if (action === 'remove') { app.cart.splice(Number(el.dataset.index), 1); render(); }
            if (action === 'clear-cart') { app.cart = []; render(); }
            if (action === 'confirm-order') {
                const confirmed = window.confirm(`Confirmar pedido por ${money(app.cart.reduce((s,i)=>s+i.price*i.quantity,0))}? El pedido quedará Pendiente.`);
                if (!confirmed) return;
                el.disabled = true; el.textContent = 'Registrando…';
                const response = await api.post('agregar_pedido', { id_usuario: app.user.id, items: app.cart.map((item) => ({ id_inventario: Number(item.variantId), cantidad: item.quantity })) });
                app.cart = []; await loadOrders(); app.view = 'orders'; setMessage(`Pedido #${response.id_pedido} registrado correctamente como Pendiente.`); render();
            }
            if (action === 'refresh-orders') { await loadOrders(); app.message = null; render(); }
            if (action === 'edit-product') { app.editingProduct = app.products.find((p) => Number(p.id_producto) === Number(el.dataset.id)); render(); root.querySelector('#productForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            if (action === 'cancel-edit') { app.editingProduct = null; render(); }
            if (action === 'toggle-product') { const p = app.products.find((item) => Number(item.id_producto) === Number(el.dataset.id)); if (!p) return; const updated = { ...p, activo: Number(p.activo) ? 0 : 1 }; await api.put('update_producto', updated); app.editingProduct = null; setMessage(Number(updated.activo) ? 'Producto visible en el catálogo.' : 'Producto ocultado del catálogo.'); await loadStore(); render(); }
            if (action === 'save-status') { const id = el.dataset.id; const estado = root.querySelector(`[data-order-status="${id}"]`).value; if (!window.confirm(`¿Cambiar el pedido #${id} a ${estado}?`)) return; await api.put('update_pedido', { id_pedido: Number(id), estado }); await loadOrders(); setMessage('Estado del pedido actualizado.'); render(); }
        } catch (error) { setMessage(error.message || 'No se pudo completar la acción.', 'error'); render(); }
    }));
    root.querySelector('[data-product-image]')?.addEventListener('change', (event) => {
        const file = event.currentTarget.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            event.currentTarget.value = '';
            setMessage('La imagen debe pesar menos de 5 MB.', 'error');
            render();
            return;
        }
        const preview = root.querySelector('[data-image-preview]');
        if (preview) preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Vista previa del producto">`;
    });
    root.querySelector('#productForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = Object.fromEntries(new FormData(form));
        const id = data.id_producto;
        const payload = { ...data, id_producto: Number(id), id_categoria: Number(data.id_categoria), precio: Number(data.precio), activo: form.elements.activo.checked ? 1 : 0 };
        delete payload.foto;
        const submitButton = form.querySelector('button[type="submit"]');
        try {
            const file = form.elements.foto.files?.[0];
            if (file) {
                submitButton.disabled = true;
                submitButton.textContent = 'Subiendo foto…';
                const formData = new FormData();
                formData.append('imagen', file);
                const uploaded = await api.upload('subir_imagen', formData);
                payload.imagen = uploaded.imagen;
            }
            if (id) await api.put('update_producto', payload);
            else { delete payload.id_producto; await api.post('agregar_producto', payload); }
            app.editingProduct = null;
            setMessage(id ? 'Producto y fotografía actualizados.' : 'Producto creado con su fotografía.');
            await loadStore();
            render();
        } catch (error) {
            setMessage(error.message, 'error');
            render();
        }
    });
    root.querySelector('#variantForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); try { await api.post('agregar_inventario', { ...data, id_producto: Number(data.id_producto), stock: Number(data.stock) }); setMessage('Talla añadida al inventario.'); await loadStore(); render(); } catch (error) { setMessage(error.message, 'error'); render(); } });
    root.querySelectorAll('[data-stock-form]').forEach((form) => form.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(form)); const row = app.inventory.find((item) => Number(item.id_inventario) === Number(form.dataset.stockForm)); try { await api.put('update_inventario', { id_inventario: Number(row.id_inventario), talla: row.talla, stock: Number(data.stock) }); setMessage('Existencias actualizadas.'); await loadStore(); render(); } catch (error) { setMessage(error.message, 'error'); render(); } }));
    root.querySelector('#userForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); try { await api.post('agregar_usuario', data); setMessage('Cuenta creada correctamente.'); await loadUsers(); render(); } catch (error) { setMessage(error.message, 'error'); render(); } });
}

if (app.user) enterStore(); else authScreen();
