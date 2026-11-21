<?php
declare(strict_types=1);

namespace App\Modules\Modulo4_FarmaciaLaboratorio\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use PDO;
use Throwable;

/**
 * Catálogo de farmacia, control atómico de stock, validación de recetas y facturación EPS.
 */
final class FarmaciaController
{
    public function listProductos(Request $req): never
    {
        $db = Database::getConnection();
        $search = trim((string)$req->query('search', ''));
        $user = JwtAuth::currentUser();
        $whereActive = ($user && $user['rol'] === 'admin' && !empty($req->query('all'))) ? '1=1' : 'activo = 1';

        if ($search !== '') {
            $stmt = $db->prepare("SELECT * FROM productos_farmacia WHERE {$whereActive} AND (nombre LIKE ? OR categoria LIKE ?) ORDER BY id ASC");
            $like = "%{$search}%";
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $db->query("SELECT * FROM productos_farmacia WHERE {$whereActive} ORDER BY id ASC");
        }

        Response::json(['ok' => true, 'productos' => $stmt->fetchAll()]);
    }

    public function storeProducto(Request $req): never
    {
        JwtAuth::requireAuth(['admin']);
        $body = $req->body();
        $nombre = trim((string)($body['nombre'] ?? ''));
        $categoria = trim((string)($body['categoria'] ?? 'General'));
        $precio = (float)($body['precio'] ?? 0);
        $stock = (int)($body['stock'] ?? 0);
        $reqReceta = !empty($body['requiere_receta']) ? 1 : 0;
        $imagen = trim((string)($body['imagen'] ?? 'img/farmacia.png'));

        if ($nombre === '' || $precio <= 0) {
            Response::error('Nombre y precio mayor a 0 son obligatorios.', 422);
        }

        $db = Database::getConnection();
        $ins = $db->prepare('INSERT INTO productos_farmacia (nombre, categoria, precio, stock, requiere_receta, imagen) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->execute([$nombre, $categoria, $precio, $stock, $reqReceta, $imagen]);

        Response::json(['ok' => true, 'message' => 'Producto creado.', 'id' => (int)$db->lastInsertId()], 201);
    }

    public function updateProducto(Request $req, int $id): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $body = $req->body();

        $db = Database::getConnection();
        $upd = $db->prepare('UPDATE productos_farmacia SET nombre = COALESCE(?, nombre), categoria = COALESCE(?, categoria), precio = COALESCE(?, precio), stock = COALESCE(?, stock), requiere_receta = COALESCE(?, requiere_receta), activo = COALESCE(?, activo) WHERE id = ?');
        $upd->execute([
            $body['nombre'] ?? null,
            $body['categoria'] ?? null,
            isset($body['precio']) ? (float)$body['precio'] : null,
            isset($body['stock']) ? (int)$body['stock'] : null,
            isset($body['requiere_receta']) ? (int)$body['requiere_receta'] : null,
            isset($body['activo']) ? (int)$body['activo'] : null,
            $id,
        ]);

        AuditService::log($user, 'ACTUALIZAR_STOCK_PRODUCTO', 'productos_farmacia', (string)$id, 'Actualización de producto/stock en farmacia', 200);
        Response::json(['ok' => true, 'message' => 'Producto y stock actualizados.']);
    }

    public function listPedidos(): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();

        if ($user['rol'] === 'paciente') {
            $dni = $user['paciente']['dni'] ?? '';
            $stmt = $db->prepare('SELECT * FROM pedidos_farmacia WHERE usuario_id = ? OR dni_cliente = ? ORDER BY created_at DESC');
            $stmt->execute([$user['id'], $dni]);
        } else {
            $stmt = $db->query('SELECT * FROM pedidos_farmacia ORDER BY created_at DESC LIMIT 100');
        }

        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$ped) {
            $stIt = $db->prepare('SELECT * FROM pedido_items WHERE pedido_id = ?');
            $stIt->execute([$ped['id']]);
            $ped['items'] = $stIt->fetchAll();
        }

        Response::json(['ok' => true, 'pedidos' => $pedidos]);
    }

    public function storePedido(Request $req): never
    {
        $user = JwtAuth::currentUser();
        $body = $req->body();

        $items = $body['items'] ?? [];
        $dni = trim((string)($body['dni'] ?? $user['paciente']['dni'] ?? ''));
        $nombreCliente = trim((string)($body['nombre'] ?? $user['nombre'] ?? 'Cliente Farmacia'));
        $metodoPago = trim((string)($body['metodo_pago'] ?? 'tarjeta_visa'));
        $codigoReceta = strtoupper(trim((string)($body['codigo_receta'] ?? '')));
        $usarEps = !empty($body['usar_eps']);

        if (empty($items) || !is_array($items)) {
            Response::error('El carrito de compras está vacío.', 422);
        }
        if (!preg_match('/^\d{8}$/', $dni)) {
            Response::error('Ingrese un DNI válido de 8 dígitos para la emisión de la boleta electrónica.', 422, ['dni' => 'DNI de 8 dígitos requerido']);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            [$subtotal, $exigeReceta, $nombresConReceta, $itemsProcesados] = $this->lockAndCollectItems($db, $items);

            if ($exigeReceta) {
                $this->validateReceta($db, $codigoReceta, $nombresConReceta);
            }

            foreach ($itemsProcesados as $ip) {
                $db->prepare('UPDATE productos_farmacia SET stock = stock - ? WHERE id = ?')
                   ->execute([$ip['cantidad'], $ip['producto_id']]);
            }

            [$descuentoEps, $montoNoCubierto] = $this->computeEpsCoverage($db, $dni, $subtotal, $usarEps);
            $totalPagado = $montoNoCubierto;
            $codigoBoleta = 'BOL-F' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

            $insPed = $db->prepare('INSERT INTO pedidos_farmacia (codigo_boleta, usuario_id, dni_cliente, nombre_cliente, subtotal, descuento_eps, total_pagado, monto_no_cubierto, metodo_pago, codigo_receta, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pagado")');
            $insPed->execute([
                $codigoBoleta,
                $user['id'] ?? null,
                $dni,
                $nombreCliente,
                $subtotal,
                $descuentoEps,
                $totalPagado,
                $montoNoCubierto,
                $metodoPago,
                $codigoReceta ?: null,
            ]);
            $pedidoId = (int)$db->lastInsertId();

            $insItem = $db->prepare('INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($itemsProcesados as $ip) {
                $insItem->execute([$pedidoId, $ip['producto_id'], $ip['nombre'], $ip['cantidad'], $ip['precio'], $ip['subtotal']]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        AuditService::log($user, 'COMPRA_FARMACIA', 'pedidos_farmacia', $codigoBoleta, "Pedido pagado S/ {$totalPagado} (DNI {$dni})", 201);

        Response::json([
            'ok' => true,
            'message' => 'Pago aprobado y boleta electrónica generada.',
            'boleta' => [
                'pedido_id' => $pedidoId,
                'codigo_boleta' => $codigoBoleta,
                'dni_cliente' => $dni,
                'nombre_cliente' => $nombreCliente,
                'subtotal' => $subtotal,
                'descuento_eps' => $descuentoEps,
                'total_pagado' => $totalPagado,
                'metodo_pago' => $metodoPago,
                'codigo_receta' => $codigoReceta ?: null,
                'fecha' => date('Y-m-d H:i:s'),
                'items' => $itemsProcesados,
            ],
        ], 201);
    }

    /** @return array{0: float, 1: bool, 2: list<string>, 3: list<array<string, mixed>>} */
    private function lockAndCollectItems(PDO $db, array $items): array
    {
        $subtotal = 0.0;
        $exigeReceta = false;
        $nombresConReceta = [];
        $itemsProcesados = [];

        foreach ($items as $item) {
            $prodId = (int)($item['id'] ?? $item['producto_id'] ?? 0);
            $cant = (int)($item['quantity'] ?? $item['cantidad'] ?? 1);
            if ($prodId <= 0 || $cant <= 0) {
                continue;
            }

            $stmtP = $db->prepare('SELECT * FROM productos_farmacia WHERE id = ? AND activo = 1 FOR UPDATE');
            $stmtP->execute([$prodId]);
            $prod = $stmtP->fetch();
            if (!$prod) {
                $db->rollBack();
                Response::error("El producto ID {$prodId} no existe.", 404);
            }
            if ((int)$prod['stock'] < $cant) {
                $db->rollBack();
                Response::error("Stock insuficiente para {$prod['nombre']} (Disponible: {$prod['stock']}, Solicitado: {$cant}).", 409);
            }
            if ((int)$prod['requiere_receta'] === 1) {
                $exigeReceta = true;
                $nombresConReceta[] = $prod['nombre'];
            }

            $lineaSub = (float)$prod['precio'] * $cant;
            $subtotal += $lineaSub;
            $itemsProcesados[] = [
                'producto_id' => (int)$prod['id'],
                'nombre' => $prod['nombre'],
                'cantidad' => $cant,
                'precio' => (float)$prod['precio'],
                'subtotal' => $lineaSub,
            ];
        }

        return [$subtotal, $exigeReceta, $nombresConReceta, $itemsProcesados];
    }

    /** @param list<string> $nombresConReceta */
    private function validateReceta(PDO $db, string $codigoReceta, array $nombresConReceta): void
    {
        if ($codigoReceta === '') {
            $db->rollBack();
            Response::error('Los siguientes medicamentos exigen receta médica vigente (' . implode(', ', $nombresConReceta) . '). Ingrese su código de receta digital (ej. REC-2026-001).', 422, [
                'codigo_receta' => 'Receta médica obligatoria para antibióticos/medicamentos controlados.',
            ]);
        }
        $stmtRec = $db->prepare('SELECT id FROM recetas WHERE codigo_receta = ? AND estado = "vigente" FOR UPDATE');
        $stmtRec->execute([$codigoReceta]);
        if (!$stmtRec->fetch()) {
            $db->rollBack();
            Response::error("El código de receta '{$codigoReceta}' no existe o ya fue utilizado/vencido.", 422, [
                'codigo_receta' => 'Código de receta inválido o vencido.',
            ]);
        }
    }

    /** @return array{0: float, 1: float} */
    private function computeEpsCoverage(PDO $db, string $dni, float $subtotal, bool $usarEps): array
    {
        if (!$usarEps) {
            return [0.0, $subtotal];
        }
        $stmtPac = $db->prepare('SELECT tiene_seguro, cobertura_eps_pct FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtPac->execute([$dni]);
        $pac = $stmtPac->fetch();
        if ($pac && (int)$pac['tiene_seguro'] === 1 && (float)$pac['cobertura_eps_pct'] > 0) {
            $descuento = round($subtotal * ((float)$pac['cobertura_eps_pct'] / 100.0), 2);
            return [$descuento, round($subtotal - $descuento, 2)];
        }
        return [0.0, $subtotal];
    }
}
