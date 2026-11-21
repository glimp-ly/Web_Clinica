<?php
declare(strict_types=1);

namespace App\Modules\Modulo3_PortalMedico\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class MedicoController
{
    public function miResumen(Request $req): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin']);
        $db = Database::getConnection();
        $medicoId = (int)($user['medico']['id'] ?? $req->query('medico_id', 1));

        $stmtM = $db->prepare('SELECT * FROM medicos WHERE id = ? LIMIT 1');
        $stmtM->execute([$medicoId]);
        $medico = $stmtM->fetch();

        $hoy = date('Y-m-d');
        $stmtHoy = $db->prepare('SELECT c.*, e.nombre AS especialidad_nombre, p.tipo_sangre, p.alergias, p.tiene_seguro, p.aseguradora,
                                 (SELECT COUNT(*) FROM historias_clinicas h WHERE h.cita_id = c.id) AS tiene_historia
                                 FROM citas c
                                 JOIN especialidades e ON e.id = c.especialidad_id
                                 LEFT JOIN pacientes p ON p.dni = c.dni_paciente
                                 WHERE c.medico_id = ? AND c.fecha_cita = ?
                                 ORDER BY c.hora_cita ASC');
        $stmtHoy->execute([$medicoId, $hoy]);
        $citasHoy = $stmtHoy->fetchAll();

        $stmtProx = $db->prepare('SELECT c.*, e.nombre AS especialidad_nombre,
                                  (SELECT COUNT(*) FROM historias_clinicas h WHERE h.cita_id = c.id) AS tiene_historia
                                  FROM citas c
                                  JOIN especialidades e ON e.id = c.especialidad_id
                                  WHERE c.medico_id = ? AND c.fecha_cita > ? AND c.estado IN ("programada", "reprogramada", "en_atencion")
                                  ORDER BY c.fecha_cita ASC, c.hora_cita ASC LIMIT 30');
        $stmtProx->execute([$medicoId, $hoy]);
        $citasProximas = $stmtProx->fetchAll();

        $stmtTodas = $db->prepare('SELECT c.*, e.nombre AS especialidad_nombre,
                                   (SELECT COUNT(*) FROM historias_clinicas h WHERE h.cita_id = c.id) AS tiene_historia
                                   FROM citas c
                                   JOIN especialidades e ON e.id = c.especialidad_id
                                   WHERE c.medico_id = ?
                                   ORDER BY c.fecha_cita DESC, c.hora_cita ASC LIMIT 60');
        $stmtTodas->execute([$medicoId]);
        $todasCitas = $stmtTodas->fetchAll();

        $stmtStats = $db->prepare('SELECT 
            COUNT(*) AS total_citas,
            SUM(CASE WHEN estado = "finalizada" THEN 1 ELSE 0 END) AS atendidas,
            SUM(CASE WHEN estado IN ("programada", "reprogramada", "en_atencion") THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = "no_show" THEN 1 ELSE 0 END) AS no_shows,
            SUM(CASE WHEN estado = "cancelada" THEN 1 ELSE 0 END) AS canceladas
            FROM citas WHERE medico_id = ?');
        $stmtStats->execute([$medicoId]);
        $stats = $stmtStats->fetch();

        $stmtDisp = $db->prepare('SELECT * FROM medico_disponibilidad WHERE medico_id = ? ORDER BY fecha DESC, hora_inicio ASC');
        $stmtDisp->execute([$medicoId]);

        $stmtPac = $db->prepare('SELECT DISTINCT p.* FROM pacientes p JOIN citas c ON c.dni_paciente = p.dni WHERE c.medico_id = ? ORDER BY p.apellidos ASC');
        $stmtPac->execute([$medicoId]);

        $stmtLab = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE medico_id = ? ORDER BY created_at DESC LIMIT 20');
        $stmtLab->execute([$medicoId]);

        $stmtRec = $db->prepare('SELECT r.*, p.nombre AS paciente_nombre, p.apellidos AS paciente_apellidos FROM recetas r LEFT JOIN pacientes p ON p.dni = r.dni_paciente WHERE r.medico_id = ? ORDER BY r.created_at DESC LIMIT 20');
        $stmtRec->execute([$medicoId]);

        $consultasTotales = (int)($medico['consultas_realizadas'] ?? 0) + (int)($stats['atendidas'] ?? 0);
        $horasAtendidas = round(((int)($stats['atendidas'] ?? 0) * 0.5) + ((int)($medico['anios_clinica'] ?? 1) * 180), 1);

        Response::json([
            'ok' => true,
            'medico' => $medico,
            'citas_hoy' => $citasHoy,
            'citas_proximas' => $citasProximas,
            'todas_citas' => $todasCitas,
            'disponibilidad' => $stmtDisp->fetchAll(),
            'mis_pacientes' => $stmtPac->fetchAll(),
            'ordenes_lab' => $stmtLab->fetchAll(),
            'recetas' => $stmtRec->fetchAll(),
            'estadisticas' => [
                'citas_registradas' => (int)($stats['total_citas'] ?? 0),
                'atendidas' => (int)($stats['atendidas'] ?? 0),
                'pendientes' => (int)($stats['pendientes'] ?? 0),
                'no_shows' => (int)($stats['no_shows'] ?? 0),
                'canceladas' => (int)($stats['canceladas'] ?? 0),
                'consultas_historicas' => $consultasTotales,
                'horas_acumuladas' => $horasAtendidas,
            ],
        ]);
    }

    public function disponibilidad(Request $req): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin']);
        $db = Database::getConnection();
        $medicoId = (int)($user['medico']['id'] ?? $req->query('medico_id', 1));

        if ($req->method === 'GET') {
            $stmt = $db->prepare('SELECT * FROM medico_disponibilidad WHERE medico_id = ? ORDER BY fecha ASC, hora_inicio ASC');
            $stmt->execute([$medicoId]);
            Response::json(['ok' => true, 'disponibilidad' => $stmt->fetchAll()]);
        }

        $body = $req->body();
        if (!empty($body['medico_id']) && $user['rol'] === 'admin') {
            $medicoId = (int)$body['medico_id'];
        }

        if (isset($body['turno_manana']) || isset($body['turno_tarde']) || isset($body['dias_atencion'])) {
            $tManana = !empty($body['turno_manana']) ? 1 : 0;
            $tTarde = !empty($body['turno_tarde']) ? 1 : 0;
            $dias = trim((string)($body['dias_atencion'] ?? 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado'));
            $db->prepare('UPDATE medicos SET turno_manana = ?, turno_tarde = ?, dias_atencion = ? WHERE id = ?')
               ->execute([$tManana, $tTarde, $dias, $medicoId]);
        }

        if (!empty($body['fecha'])) {
            $fecha = trim((string)$body['fecha']);
            $turno = in_array($body['turno'] ?? '', ['manana', 'tarde', 'completo'], true) ? $body['turno'] : 'manana';
            $horaIni = trim((string)($body['hora_inicio'] ?? ($turno === 'tarde' ? '14:00:00' : '08:00:00')));
            $horaFin = trim((string)($body['hora_fin'] ?? ($turno === 'tarde' ? '19:00:00' : '13:00:00')));
            $estado = in_array($body['estado'] ?? '', ['disponible', 'bloqueado', 'ausencia'], true) ? $body['estado'] : 'bloqueado';
            $motivo = trim((string)($body['motivo'] ?? 'Bloqueo de horario'));

            $db->prepare('INSERT INTO medico_disponibilidad (medico_id, fecha, turno, hora_inicio, hora_fin, estado, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$medicoId, $fecha, $turno, $horaIni, $horaFin, $estado, $motivo]);
        }

        AuditService::log($user, 'ACTUALIZAR_DISPONIBILIDAD_MEDICO', 'medico_disponibilidad', (string)$medicoId, 'Actualización de agenda y disponibilidad médica', 200);
        Response::json(['ok' => true, 'message' => 'Disponibilidad actualizada correctamente.']);
    }

    public function deleteDisponibilidad(int $dispId): never
    {
        JwtAuth::requireAuth(['medico', 'admin']);
        $db = Database::getConnection();
        $db->prepare('DELETE FROM medico_disponibilidad WHERE id = ?')->execute([$dispId]);
        Response::json(['ok' => true, 'message' => 'Bloque eliminado.']);
    }

    public function index(Request $req): never
    {
        $db = Database::getConnection();
        $search = trim((string)$req->query('search', ''));
        $esp = trim((string)$req->query('especialidad', ''));
        $user = JwtAuth::currentUser();
        $showAll = ($user && $user['rol'] === 'admin' && !empty($req->query('all')));

        $sql = 'SELECT m.*, e.clave AS especialidad_clave, u.email AS email
                FROM medicos m
                JOIN especialidades e ON e.id = m.especialidad_id
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                WHERE ' . ($showAll ? '1=1' : 'm.activo = 1');
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (m.nombre LIKE ? OR m.especialidad_nombre LIKE ? OR m.descripcion LIKE ?)';
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($esp !== '') {
            $sql .= ' AND (e.clave = ? OR m.especialidad_id = ?)';
            $params[] = $esp;
            $params[] = $esp;
        }
        $sql .= ' ORDER BY m.id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::json(['ok' => true, 'medicos' => $stmt->fetchAll()]);
    }

    public function store(Request $req): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $db = Database::getConnection();
        $body = $req->body();

        $nombre = trim((string)($body['nombre'] ?? ''));
        $cmp = trim((string)($body['cmp'] ?? ''));
        $dni = trim((string)($body['dni'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? 'Medico123!');
        $espId = (int)($body['especialidad_id'] ?? 1);
        $descripcion = trim((string)($body['descripcion'] ?? 'Especialista médico de la Clínica Centenario del Norte.'));
        $exp = (int)($body['experiencia_anios'] ?? 5);
        $aniosClinica = (int)($body['anios_clinica'] ?? 1);
        $telefono = trim((string)($body['telefono'] ?? '999888777'));
        $imagen = trim((string)($body['imagen'] ?? 'img/doctor1.jpg'));
        $turnoM = isset($body['turno_manana']) ? (int)$body['turno_manana'] : 1;
        $turnoT = isset($body['turno_tarde']) ? (int)$body['turno_tarde'] : 1;
        $diasAt = trim((string)($body['dias_atencion'] ?? 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado'));

        if ($nombre === '' || $cmp === '' || !preg_match('/^\d{8}$/', $dni)) {
            Response::error('Nombre, CMP y DNI (8 dígitos) son obligatorios.', 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('El correo institucional del médico es inválido.', 422);
        }

        $stmtDup = $db->prepare('SELECT id FROM medicos WHERE cmp = ? OR dni = ? LIMIT 1');
        $stmtDup->execute([$cmp, $dni]);
        if ($stmtDup->fetch()) {
            Response::error('Ya existe un médico registrado con ese CMP o DNI.', 409);
        }

        if ($email !== '') {
            $stmtEmail = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
            $stmtEmail->execute([$email]);
            if ($stmtEmail->fetch()) {
                Response::error('El correo electrónico ya está registrado en el sistema.', 409);
            }
        }

        $stmtE = $db->prepare('SELECT nombre FROM especialidades WHERE id = ? LIMIT 1');
        $stmtE->execute([$espId]);
        $espNombre = (string)($stmtE->fetchColumn() ?: 'Medicina General');

        $db->beginTransaction();
        try {
            $usuarioId = null;
            if ($email !== '') {
                $hash = password_hash($password !== '' ? $password : 'Medico123!', PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol) VALUES (?, ?, ?, "medico")')
                   ->execute([$email, $hash, $nombre]);
                $usuarioId = (int)$db->lastInsertId();
            }
            $db->prepare('INSERT INTO medicos (usuario_id, cmp, dni, nombre, especialidad_id, especialidad_nombre, descripcion, imagen, experiencia_anios, anios_clinica, telefono, turno_manana, turno_tarde, dias_atencion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$usuarioId, $cmp, $dni, $nombre, $espId, $espNombre, $descripcion, $imagen, $exp, $aniosClinica, $telefono, $turnoM, $turnoT, $diasAt]);
            $id = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        AuditService::log($user, 'CREAR_MEDICO', 'medicos', (string)$id, "Médico creado: {$nombre} ({$cmp})", 201);
        Response::json(['ok' => true, 'message' => 'Médico registrado en el staff.', 'id' => $id], 201);
    }

    public function updateOrDelete(Request $req, int $id): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $db = Database::getConnection();

        if ($req->method === 'DELETE') {
            $db->prepare('UPDATE medicos SET activo = 0 WHERE id = ?')->execute([$id]);
            AuditService::log($user, 'DESACTIVAR_MEDICO', 'medicos', (string)$id, 'Médico desactivado por administrador', 200);
            Response::json(['ok' => true, 'message' => 'Médico desactivado del catálogo.']);
        }

        $body = $req->body();
        $espId = isset($body['especialidad_id']) ? (int)$body['especialidad_id'] : null;
        $espNombre = null;
        if ($espId !== null) {
            $stmtE = $db->prepare('SELECT nombre FROM especialidades WHERE id = ? LIMIT 1');
            $stmtE->execute([$espId]);
            $espNombre = (string)($stmtE->fetchColumn() ?: 'Medicina General');
        }

        $upd = $db->prepare('UPDATE medicos SET nombre = COALESCE(?, nombre), cmp = COALESCE(?, cmp), especialidad_id = COALESCE(?, especialidad_id), especialidad_nombre = COALESCE(?, especialidad_nombre), descripcion = COALESCE(?, descripcion), experiencia_anios = COALESCE(?, experiencia_anios), telefono = COALESCE(?, telefono), turno_manana = COALESCE(?, turno_manana), turno_tarde = COALESCE(?, turno_tarde), dias_atencion = COALESCE(?, dias_atencion), activo = COALESCE(?, activo) WHERE id = ?');
        $upd->execute([
            $body['nombre'] ?? null,
            $body['cmp'] ?? null,
            $espId,
            $espNombre,
            $body['descripcion'] ?? null,
            isset($body['experiencia_anios']) ? (int)$body['experiencia_anios'] : null,
            $body['telefono'] ?? null,
            isset($body['turno_manana']) ? (int)$body['turno_manana'] : null,
            isset($body['turno_tarde']) ? (int)$body['turno_tarde'] : null,
            $body['dias_atencion'] ?? null,
            isset($body['activo']) ? (int)$body['activo'] : null,
            $id,
        ]);

        AuditService::log($user, 'ACTUALIZAR_MEDICO', 'medicos', (string)$id, 'Datos/horarios de médico actualizados', 200);
        Response::json(['ok' => true, 'message' => 'Datos del médico actualizados.']);
    }
}
