<?php
declare(strict_types=1);

namespace App\Modules\Modulo4_FarmaciaLaboratorio\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * Catálogo de laboratorio clínico, generación de órdenes, publicación y consulta segura de resultados.
 */
final class LaboratorioController
{
    public function catalogo(): never
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM catalogo_laboratorio WHERE activo = 1 ORDER BY id ASC');
        Response::json(['ok' => true, 'analisis' => $stmt->fetchAll()]);
    }

    public function listOrdenes(): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();

        if ($user['rol'] === 'paciente') {
            $stmt = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE dni_paciente = ? ORDER BY created_at DESC');
            $stmt->execute([$user['paciente']['dni'] ?? '']);
        } elseif ($user['rol'] === 'medico') {
            $medicoId = (int)($user['medico']['id'] ?? 0);
            $stmt = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE medico_id = ? OR dni_paciente IN (SELECT dni_paciente FROM citas WHERE medico_id = ?) ORDER BY created_at DESC');
            $stmt->execute([$medicoId, $medicoId]);
        } else {
            $stmt = $db->query('SELECT * FROM ordenes_laboratorio ORDER BY created_at DESC');
        }

        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['analisis'] = json_decode($r['analisis_json'], true) ?: [];
            $r['valores_resultado'] = $r['valores_resultado_json'] ? json_decode($r['valores_resultado_json'], true) : [];
        }

        Response::json(['ok' => true, 'ordenes' => $rows]);
    }

    public function storeOrden(Request $req): never
    {
        $user = JwtAuth::currentUser();
        $body = $req->body();

        $dni = trim((string)($body['dni_paciente'] ?? $body['dni'] ?? $user['paciente']['dni'] ?? ''));
        $nombrePaciente = trim((string)($body['nombre_paciente'] ?? $body['nombre'] ?? $user['nombre'] ?? ''));
        $analisisItems = $body['analisis'] ?? [];
        $metodoPago = trim((string)($body['metodo_pago'] ?? 'tarjeta_visa'));
        $citaId = !empty($body['cita_id']) ? (int)$body['cita_id'] : null;

        if (!preg_match('/^\d{8}$/', $dni) || empty($analisisItems)) {
            Response::error('DNI válido de 8 dígitos y al menos un análisis son obligatorios.', 422);
        }

        $db = Database::getConnection();
        $stmtP = $db->prepare('SELECT id, nombre, apellidos FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtP->execute([$dni]);
        $pac = $stmtP->fetch();
        $pacienteId = $pac ? (int)$pac['id'] : null;
        if ($nombrePaciente === '' && $pac) {
            $nombrePaciente = "{$pac['nombre']} {$pac['apellidos']}";
        } elseif ($nombrePaciente === '') {
            $nombrePaciente = "Paciente DNI {$dni}";
        }

        $total = 0.0;
        $detalle = [];
        $stmtCat = $db->prepare('SELECT precio FROM catalogo_laboratorio WHERE nombre = ? LIMIT 1');
        foreach ($analisisItems as $a) {
            $nombreA = trim((string)($a['nombre'] ?? 'Análisis Clínico'));
            $cant = (int)($a['cantidad'] ?? 1);
            if ($cant <= 0) {
                Response::error("La cantidad para '{$nombreA}' debe ser mayor a 0.", 422);
            }
            $stmtCat->execute([$nombreA]);
            $precio = (float)($stmtCat->fetchColumn() ?: ($a['precio'] ?? 40.00));
            $total += $precio * $cant;
            $detalle[] = ['nombre' => $nombreA, 'precio' => $precio, 'cantidad' => $cant];
        }

        $medicoId = ($user && $user['rol'] === 'medico') ? (int)($user['medico']['id'] ?? 1) : (!empty($body['medico_id']) ? (int)$body['medico_id'] : null);
        $codigoOrden = 'LAB-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $codigoBoleta = 'BOL-L' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $ins = $db->prepare('INSERT INTO ordenes_laboratorio (codigo_orden, cita_id, paciente_id, dni_paciente, nombre_paciente, medico_id, analisis_json, total, metodo_pago, codigo_boleta, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente_muestra")');
        $ins->execute([$codigoOrden, $citaId, $pacienteId, $dni, $nombrePaciente, $medicoId, json_encode($detalle, JSON_UNESCAPED_UNICODE), $total, $metodoPago, $codigoBoleta]);
        $ordenId = (int)$db->lastInsertId();

        AuditService::log($user, 'CREAR_ORDEN_LABORATORIO', 'ordenes_laboratorio', $codigoOrden, "Orden de laboratorio generada para DNI {$dni} (Total S/ {$total})", 201);

        Response::json([
            'ok' => true,
            'message' => 'Orden de laboratorio registrada con éxito.',
            'orden' => [
                'id' => $ordenId,
                'codigo_orden' => $codigoOrden,
                'codigo_boleta' => $codigoBoleta,
                'dni_paciente' => $dni,
                'nombre_paciente' => $nombrePaciente,
                'total' => $total,
                'analisis' => $detalle,
                'estado' => 'pendiente_muestra',
            ],
        ], 201);
    }

    public function updateOrden(Request $req, int $ordenId): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin']);
        $body = $req->body();

        $estado = in_array($body['estado'] ?? '', ['pendiente_muestra', 'en_proceso', 'publicado'], true) ? $body['estado'] : 'publicado';
        $resumen = trim((string)($body['resultado_resumen'] ?? 'Valores bioquímicos dentro de límites normales.'));
        $observaciones = trim((string)($body['observaciones_lab'] ?? 'Validado por Laboratorio Clínico Centenario.'));
        $valores = $body['valores_resultado'] ?? [
            ['parametro' => 'Resultado General', 'valor' => $resumen, 'referencia' => 'Valores estándar', 'estado' => 'Normal'],
        ];

        $db = Database::getConnection();
        $db->prepare('UPDATE ordenes_laboratorio SET estado = ?, resultado_resumen = ?, valores_resultado_json = ?, observaciones_lab = ?, fecha_publicacion = NOW() WHERE id = ?')
           ->execute([$estado, $resumen, json_encode($valores, JSON_UNESCAPED_UNICODE), $observaciones, $ordenId]);

        $stmtO = $db->prepare('SELECT codigo_orden FROM ordenes_laboratorio WHERE id = ?');
        $stmtO->execute([$ordenId]);
        $ord = $stmtO->fetch();

        AuditService::log($user, 'PUBLICAR_RESULTADO_LAB', 'ordenes_laboratorio', $ord['codigo_orden'] ?? (string)$ordenId, 'Publicación de resultados clínicos de laboratorio', 200);
        Response::json(['ok' => true, 'message' => 'Resultados de laboratorio publicados correctamente.']);
    }

    public function verResultado(Request $req, string $codigo): never
    {
        $codigo = strtoupper(trim($codigo));
        $dniQuery = trim((string)$req->query('dni', ''));
        $user = JwtAuth::currentUser();

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT o.*, m.nombre AS medico_nombre FROM ordenes_laboratorio o LEFT JOIN medicos m ON m.id = o.medico_id WHERE o.codigo_orden = ? LIMIT 1');
        $stmt->execute([$codigo]);
        $orden = $stmt->fetch();
        if (!$orden) {
            Response::error("No se encontró ninguna orden de laboratorio con el código {$codigo}.", 404);
        }

        if (!$this->isAuthorizedToViewResult($db, $user, $orden, $dniQuery)) {
            AuditService::log($user, 'ACCESO_DENEGADO_RESULTADO_LAB', 'ordenes_laboratorio', $codigo, 'Intento de acceso sin ser dueño ni médico tratante', 403);
            Response::error('Acceso restringido (RF-C10 / Ley 29733): Solo el titular del análisis (verificando su sesión o DNI) o su médico tratante pueden acceder a estos resultados.', 403);
        }

        $orden['analisis'] = json_decode($orden['analisis_json'], true) ?: [];
        $orden['valores_resultado'] = $orden['valores_resultado_json'] ? json_decode($orden['valores_resultado_json'], true) : [];

        AuditService::log($user, 'CONSULTA_RESULTADO_LAB', 'ordenes_laboratorio', $codigo, "Descarga/lectura de resultado de laboratorio {$codigo}", 200);
        Response::json(['ok' => true, 'resultado' => $orden]);
    }

    private function isAuthorizedToViewResult(PDO $db, ?array $user, array $orden, string $dniQuery): bool
    {
        if ($dniQuery !== '' && $dniQuery === $orden['dni_paciente']) {
            return true;
        }
        if (!$user) {
            return false;
        }
        if ($user['rol'] === 'admin') {
            return true;
        }
        if ($user['rol'] === 'paciente') {
            $userDni = $user['paciente']['dni'] ?? '';
            return $userDni === $orden['dni_paciente'] || (int)$orden['paciente_id'] === (int)($user['paciente']['id'] ?? -1);
        }
        if ($user['rol'] === 'medico') {
            $medId = (int)($user['medico']['id'] ?? 0);
            if ((int)$orden['medico_id'] === $medId) {
                return true;
            }
            $stC = $db->prepare('SELECT COUNT(*) FROM citas WHERE dni_paciente = ? AND medico_id = ?');
            $stC->execute([$orden['dni_paciente'], $medId]);
            return (int)$stC->fetchColumn() > 0;
        }
        return false;
    }
}
