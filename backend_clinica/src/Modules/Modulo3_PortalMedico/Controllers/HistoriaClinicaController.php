<?php
declare(strict_types=1);

namespace App\Modules\Modulo3_PortalMedico\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

/**
 * Consulta y registro de historias clínicas CIE-10 con control RBAC de médico tratante.
 */
final class HistoriaClinicaController
{
    public function historia(Request $req, string $dni): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin', 'paciente']);
        $db = Database::getConnection();

        $stmtP = $db->prepare('SELECT * FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtP->execute([$dni]);
        $paciente = $stmtP->fetch();
        if (!$paciente) {
            Response::error('Paciente con DNI ' . $dni . ' no encontrado.', 404);
        }

        if ($user['rol'] === 'paciente') {
            if ($req->method !== 'GET' || (int)($paciente['usuario_id'] ?? 0) !== (int)$user['id']) {
                AuditService::log($user, 'ACCESO_DENEGADO_HISTORIA', 'historias_clinicas', $dni, 'Paciente intentó acceder a historia ajena', 403);
                Response::error('No autorizado para ver la historia clínica de otro paciente.', 403);
            }
        } elseif ($user['rol'] === 'medico') {
            $medicoId = (int)($user['medico']['id'] ?? 0);
            $stmtAsig = $db->prepare('SELECT COUNT(*) FROM citas WHERE dni_paciente = ? AND medico_id = ?');
            $stmtAsig->execute([$dni, $medicoId]);
            $tieneCita = (int)$stmtAsig->fetchColumn() > 0;

            $stmtHc = $db->prepare('SELECT COUNT(*) FROM historias_clinicas WHERE dni_paciente = ? AND medico_id = ?');
            $stmtHc->execute([$dni, $medicoId]);
            $tieneHc = (int)$stmtHc->fetchColumn() > 0;

            if (!$tieneCita && !$tieneHc) {
                AuditService::log($user, 'ACCESO_DENEGADO_HISTORIA', 'historias_clinicas', $dni, "Médico ID {$medicoId} no es médico tratante del DNI {$dni}", 403);
                Response::error('Acceso denegado (RF-M04): Solo el médico tratante asignado al paciente o el administrador pueden acceder a esta historia clínica.', 403);
            }
        }

        if ($req->method === 'GET') {
            $stmtH = $db->prepare('SELECT h.*, m.nombre AS medico_nombre, m.especialidad_nombre, m.cmp
                                   FROM historias_clinicas h
                                   JOIN medicos m ON m.id = h.medico_id
                                   WHERE h.dni_paciente = ?
                                   ORDER BY h.created_at DESC');
            $stmtH->execute([$dni]);

            $stmtRec = $db->prepare('SELECT r.*, m.nombre AS medico_nombre FROM recetas r JOIN medicos m ON m.id = r.medico_id WHERE r.dni_paciente = ? ORDER BY r.created_at DESC');
            $stmtRec->execute([$dni]);

            $stmtLab = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE dni_paciente = ? ORDER BY created_at DESC');
            $stmtLab->execute([$dni]);

            AuditService::log($user, 'LECTURA_HISTORIA_CLINICA', 'historias_clinicas', $dni, "Visualización autorizada de historia clínica DNI {$dni}", 200);

            Response::json([
                'ok' => true,
                'paciente' => $paciente,
                'entradas' => $stmtH->fetchAll(),
                'recetas' => $stmtRec->fetchAll(),
                'ordenes_lab' => $stmtLab->fetchAll(),
            ]);
        }

        if ($req->method === 'POST') {
            if (!in_array($user['rol'], ['medico', 'admin'], true)) {
                Response::error('Solo médicos tratantes pueden registrar evoluciones en la historia clínica.', 403);
            }
            $body = $req->body();
            $motivo = trim((string)($body['motivo'] ?? ''));
            $anamnesis = trim((string)($body['anamnesis'] ?? ''));
            $examenFisico = trim((string)($body['examen_fisico'] ?? ''));
            $signosVitales = trim((string)($body['signos_vitales'] ?? ''));
            $cie10 = strtoupper(trim((string)($body['diagnostico_cie10'] ?? 'Z00.0')));
            $diagnostico = trim((string)($body['diagnostico'] ?? ''));
            $tratamiento = trim((string)($body['tratamiento'] ?? ''));
            $evolucion = trim((string)($body['evolucion'] ?? 'Favorable'));
            $citaId = !empty($body['cita_id']) ? (int)$body['cita_id'] : null;

            if ($motivo === '' || $anamnesis === '' || $diagnostico === '' || $tratamiento === '') {
                Response::error('Motivo, anamnesis, diagnóstico y tratamiento son obligatorios.', 422);
            }

            $medicoId = (int)($user['medico']['id'] ?? $body['medico_id'] ?? 1);
            $ins = $db->prepare('INSERT INTO historias_clinicas (paciente_id, dni_paciente, cita_id, medico_id, motivo, anamnesis, examen_fisico, signos_vitales, diagnostico_cie10, diagnostico, tratamiento, evolucion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$paciente['id'], $dni, $citaId, $medicoId, $motivo, $anamnesis, $examenFisico, $signosVitales, $cie10, $diagnostico, $tratamiento, $evolucion]);
            $hcId = (int)$db->lastInsertId();

            if (!empty($body['alergias']) || !empty($body['enfermedades'])) {
                $db->prepare('UPDATE pacientes SET alergias = COALESCE(?, alergias), enfermedades = COALESCE(?, enfermedades) WHERE id = ?')
                   ->execute([$body['alergias'] ?? null, $body['enfermedades'] ?? null, $paciente['id']]);
            }

            AuditService::log($user, 'ESCRITURA_HISTORIA_CLINICA', 'historias_clinicas', (string)$hcId, "Registro clínico CIE-10 {$cie10} para paciente DNI {$dni}", 201);

            Response::json([
                'ok' => true,
                'message' => 'Evolución registrada en la historia clínica y auditada.',
                'historia_id' => $hcId,
            ], 201);
        }

        Response::error("Método {$req->method} no soportado en historia clínica.", 405);
    }
}
