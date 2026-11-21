<?php
declare(strict_types=1);

namespace App\Modules\Modulo3_PortalMedico\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

/**
 * Emisión y consulta de recetas médicas digitales.
 */
final class RecetaController
{
    public function index(Request $req): never
    {
        $user = JwtAuth::requireAuth();
        $dni = trim((string)$req->query('dni', ''));

        $sql = 'SELECT r.*, m.nombre AS medico_nombre, m.especialidad_nombre, m.cmp, p.nombre AS paciente_nombre, p.apellidos AS paciente_apellidos
                FROM recetas r
                JOIN medicos m ON m.id = r.medico_id
                LEFT JOIN pacientes p ON p.dni = r.dni_paciente';
        $params = [];

        if ($user['rol'] === 'paciente') {
            $sql .= ' WHERE r.dni_paciente = ?';
            $params[] = $user['paciente']['dni'] ?? $dni;
        } elseif ($dni !== '') {
            $sql .= ' WHERE r.dni_paciente = ?';
            $params[] = $dni;
        }
        $sql .= ' ORDER BY r.created_at DESC';

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['medicamentos'] = json_decode($r['medicamentos_json'], true) ?: [];
        }

        Response::json(['ok' => true, 'recetas' => $rows]);
    }

    public function store(Request $req): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin']);
        $body = $req->body();

        $dni = trim((string)($body['dni_paciente'] ?? ''));
        $diagnostico = trim((string)($body['diagnostico'] ?? ''));
        $indicaciones = trim((string)($body['indicaciones'] ?? ''));
        $medicamentos = $body['medicamentos'] ?? [];
        $citaId = !empty($body['cita_id']) ? (int)$body['cita_id'] : null;

        if (!preg_match('/^\d{8}$/', $dni) || $diagnostico === '' || empty($medicamentos)) {
            Response::error('DNI del paciente, diagnóstico y al menos un medicamento son obligatorios.', 422);
        }

        $db = Database::getConnection();
        $stmtP = $db->prepare('SELECT id, email FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtP->execute([$dni]);
        $pac = $stmtP->fetch();
        if (!$pac) {
            Response::error('Paciente con DNI ' . $dni . ' no encontrado.', 404);
        }

        $medicoId = (int)($user['medico']['id'] ?? $body['medico_id'] ?? 1);
        $codigoReceta = 'REC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $emision = date('Y-m-d');
        $vencimiento = date('Y-m-d', strtotime('+15 days'));

        $ins = $db->prepare('INSERT INTO recetas (codigo_receta, cita_id, paciente_id, dni_paciente, medico_id, diagnostico, medicamentos_json, indicaciones, estado, fecha_emision, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "vigente", ?, ?)');
        $ins->execute([$codigoReceta, $citaId, $pac['id'], $dni, $medicoId, $diagnostico, json_encode($medicamentos, JSON_UNESCAPED_UNICODE), $indicaciones, $emision, $vencimiento]);
        $recetaId = (int)$db->lastInsertId();

        if (!empty($pac['email'])) {
            AuditService::queueNotification($pac['email'], 'email', "Nueva Receta Digital {$codigoReceta}", "Su médico ha emitido la receta digital {$codigoReceta}. Puede utilizar este código en nuestra Farmacia Virtual.", 'receta', $recetaId);
        }
        AuditService::log($user, 'EMITIR_RECETA_DIGITAL', 'recetas', $codigoReceta, "Receta emitida para DNI {$dni}", 201);

        Response::json([
            'ok' => true,
            'message' => 'Receta digital emitida exitosamente.',
            'codigo_receta' => $codigoReceta,
            'id' => $recetaId,
        ], 201);
    }
}
