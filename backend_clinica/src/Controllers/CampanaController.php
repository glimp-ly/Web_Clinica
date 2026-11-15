<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Gestión de campañas de salud e inscripciones con control de cupos concurrentes.
 */
final class CampanaController
{
    public function index(): never
    {
        $db = Database::getConnection();
        $rows = $db->query('SELECT * FROM campanas ORDER BY id ASC')->fetchAll();
        foreach ($rows as &$r) {
            $r['cupos_disponibles'] = max(0, (int)$r['cupos_totales'] - (int)$r['cupos_ocupados']);
        }
        Response::json(['ok' => true, 'campanas' => $rows]);
    }

    public function store(Request $req): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $body = $req->body();

        $titulo = trim((string)($body['titulo'] ?? ''));
        $descripcion = trim((string)($body['descripcion'] ?? ''));
        $icono = trim((string)($body['icono'] ?? 'fas fa-heartbeat'));
        $fechaInicio = !empty($body['fecha_inicio']) ? trim((string)$body['fecha_inicio']) : date('Y-m-d H:i:s');
        $fechaFin = !empty($body['fecha_fin']) ? trim((string)$body['fecha_fin']) : date('Y-m-d H:i:s', strtotime('+5 days'));
        $rango = trim((string)($body['rango_fechas_texto'] ?? ''));
        if ($rango === '') {
            $rango = date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
        }
        $cuposTotales = (int)($body['cupos_totales'] ?? $body['cupo_maximo'] ?? 500);
        $estado = in_array($body['estado'] ?? '', ['activa', 'pronto', 'finalizada'], true) ? $body['estado'] : 'activa';

        if ($titulo === '' || $cuposTotales <= 0) {
            Response::error('Título y cupos totales (>0) son obligatorios.', 422);
        }

        $db = Database::getConnection();
        $ins = $db->prepare('INSERT INTO campanas (titulo, descripcion, icono, rango_fechas_texto, fecha_inicio, fecha_fin, cupos_totales, cupos_ocupados, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
        $ins->execute([$titulo, $descripcion, $icono, $rango, $fechaInicio, $fechaFin, $cuposTotales, $estado]);
        $id = (int)$db->lastInsertId();

        AuditService::log($user, 'CREAR_CAMPANA', 'campanas', (string)$id, "Campaña creada: {$titulo}", 201);
        Response::json(['ok' => true, 'message' => 'Campaña creada exitosamente.', 'id' => $id], 201);
    }

    public function update(Request $req, int $id): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $body = $req->body();
        $cuposTot = isset($body['cupos_totales']) ? (int)$body['cupos_totales'] : (isset($body['cupo_maximo']) ? (int)$body['cupo_maximo'] : null);

        $db = Database::getConnection();
        $upd = $db->prepare('UPDATE campanas SET titulo = COALESCE(?, titulo), descripcion = COALESCE(?, descripcion), rango_fechas_texto = COALESCE(?, rango_fechas_texto), cupos_totales = COALESCE(?, cupos_totales), cupos_ocupados = COALESCE(?, cupos_ocupados), estado = COALESCE(?, estado), fecha_inicio = COALESCE(?, fecha_inicio), fecha_fin = COALESCE(?, fecha_fin) WHERE id = ?');
        $upd->execute([
            $body['titulo'] ?? null,
            $body['descripcion'] ?? null,
            $body['rango_fechas_texto'] ?? null,
            $cuposTot,
            isset($body['cupos_ocupados']) ? (int)$body['cupos_ocupados'] : null,
            $body['estado'] ?? null,
            $body['fecha_inicio'] ?? null,
            $body['fecha_fin'] ?? null,
            $id,
        ]);

        AuditService::log($user, 'ACTUALIZAR_CAMPANA', 'campanas', (string)$id, 'Actualización de cupos/estado de campaña', 200);
        Response::json(['ok' => true, 'message' => 'Campaña actualizada correctamente.']);
    }

    public function inscribir(Request $req, int $campanaId): never
    {
        $user = JwtAuth::currentUser();
        $body = $req->body();

        $dni = trim((string)($body['dni'] ?? ''));
        $nombre = trim((string)($body['nombre'] ?? $body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));

        if (!preg_match('/^\d{8}$/', $dni) || strlen($nombre) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('DNI válido de 8 dígitos, nombre completo y correo electrónico son obligatorios.', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmtC = $db->prepare('SELECT * FROM campanas WHERE id = ? FOR UPDATE');
            $stmtC->execute([$campanaId]);
            $campana = $stmtC->fetch();
            if (!$campana) {
                $db->rollBack();
                Response::error('La campaña seleccionada no existe.', 404);
            }
            if ($campana['estado'] !== 'activa') {
                $db->rollBack();
                Response::error('Esta campaña no se encuentra activa para inscripciones.', 409);
            }
            if ((int)$campana['cupos_ocupados'] >= (int)$campana['cupos_totales']) {
                $db->rollBack();
                Response::error('Lo sentimos, los cupos para esta campaña se han agotado (409 Cupo lleno).', 409);
            }

            $stmtDup = $db->prepare('SELECT id FROM campana_inscripciones WHERE campana_id = ? AND dni = ? LIMIT 1');
            $stmtDup->execute([$campanaId, $dni]);
            if ($stmtDup->fetch()) {
                $db->rollBack();
                Response::error("El DNI {$dni} ya se encuentra inscrito en la campaña '{$campana['titulo']}'.", 409);
            }

            $db->prepare('INSERT INTO campana_inscripciones (campana_id, dni, nombre, email, usuario_id) VALUES (?, ?, ?, ?, ?)')
               ->execute([$campanaId, $dni, $nombre, $email, $user['id'] ?? null]);
            $db->prepare('UPDATE campanas SET cupos_ocupados = cupos_ocupados + 1 WHERE id = ?')
               ->execute([$campanaId]);

            $nuevoOcupado = (int)$campana['cupos_ocupados'] + 1;
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        AuditService::queueNotification($email, 'email', "Inscripción Confirmada: {$campana['titulo']}", "Hola {$nombre}, tu cupo para la campaña '{$campana['titulo']}' ha sido reservado con éxito.", 'campana', $campanaId);
        AuditService::log($user, 'INSCRIPCION_CAMPANA', 'campanas', (string)$campanaId, "DNI {$dni} inscrito en campaña {$campana['titulo']}", 201);

        Response::json([
            'ok' => true,
            'message' => "¡Inscripción exitosa para {$nombre} en {$campana['titulo']}!",
            'cupos_ocupados' => $nuevoOcupado,
            'cupos_totales' => (int)$campana['cupos_totales'],
            'cupos_disponibles' => (int)$campana['cupos_totales'] - $nuevoOcupado,
        ], 201);
    }
}
