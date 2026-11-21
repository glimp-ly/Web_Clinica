<?php
declare(strict_types=1);

namespace App\Modules\Modulo2_CitasAgenda\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use PDO;
use Throwable;

/**
 * Gestión de citas médicas, disponibilidad horaria, reprogramación, cancelación y derivaciones.
 */
final class CitaController
{
    public function ocupadas(Request $req): never
    {
        $medicoId = (int)$req->query('medico_id', 0);
        $fecha = trim((string)$req->query('fecha', date('Y-m-d')));
        if ($medicoId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            Response::error('Debe indicar medico_id y fecha válidos.', 422);
        }

        $db = Database::getConnection();
        $stmtH = $db->prepare('SELECT TIME_FORMAT(hora_cita, "%H:%i") AS hora FROM citas WHERE medico_id = ? AND fecha_cita = ? AND estado NOT IN ("cancelada")');
        $stmtH->execute([$medicoId, $fecha]);
        $horasOcupadas = $stmtH->fetchAll(PDO::FETCH_COLUMN);

        $stmtB = $db->prepare('SELECT turno, TIME_FORMAT(hora_inicio, "%H:%i") AS hora_inicio, TIME_FORMAT(hora_fin, "%H:%i") AS hora_fin, estado, motivo FROM medico_disponibilidad WHERE medico_id = ? AND fecha = ? AND estado IN ("bloqueado", "ausencia")');
        $stmtB->execute([$medicoId, $fecha]);

        Response::json(['ok' => true, 'horas_ocupadas' => $horasOcupadas, 'bloqueos' => $stmtB->fetchAll()]);
    }

    public function index(Request $req): never
    {
        $user = JwtAuth::currentUser();
        $dniFilter = trim((string)$req->query('dni', ''));

        $sql = 'SELECT c.*, e.nombre AS especialidad_nombre, e.precio_consulta, m.nombre AS medico_nombre, m.cmp AS medico_cmp
                FROM citas c
                JOIN especialidades e ON e.id = c.especialidad_id
                JOIN medicos m ON m.id = c.medico_id';
        $params = [];

        if ($req->route === '/mis-citas' || ($user && $user['rol'] === 'paciente')) {
            if (!$user && $dniFilter === '') {
                Response::error('Inicie sesión o indique su DNI para consultar sus citas.', 401);
            }
            $sql .= ' WHERE (c.usuario_id = ? OR c.dni_paciente = ?)';
            $params = [$user['id'] ?? -1, $user['paciente']['dni'] ?? $dniFilter];
        } elseif ($user && $user['rol'] === 'medico') {
            $sql .= ' WHERE c.medico_id = ?';
            $params[] = (int)($user['medico']['id'] ?? 0);
        } elseif ($dniFilter !== '') {
            $sql .= ' WHERE c.dni_paciente = ?';
            $params[] = $dniFilter;
        }

        $sql .= ' ORDER BY c.fecha_cita DESC, c.hora_cita ASC';
        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::json(['ok' => true, 'citas' => $stmt->fetchAll()]);
    }

    public function store(Request $req): never
    {
        $user = JwtAuth::currentUser();
        $body = $req->body();

        $dni = trim((string)($body['dni'] ?? ''));
        $telefono = trim((string)($body['numero_cel'] ?? $body['telefono'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $espInput = trim((string)($body['especialidad'] ?? $body['especialidad_id'] ?? ''));
        $fechaInicio = trim((string)($body['fecha_inicio'] ?? $body['fecha_cita'] ?? $body['fecha'] ?? ''));
        $fechaFin = trim((string)($body['fecha_fin'] ?? $fechaInicio));
        $turno = trim((string)($body['turno'] ?? 'manana'));
        $horaCita = $this->normalizeTime((string)($body['hora_cita'] ?? ($turno === 'tarde' ? '15:00:00' : '09:00:00')));
        $motivo = trim((string)($body['motivo_consulta'] ?? 'Consulta médica especializada'));
        $aceptoTerminos = !empty($body['terminos-condiciones']) || !empty($body['acepto_terminos']);

        $errors = $this->validateBooking($dni, $telefono, $email, $espInput, $fechaInicio, $fechaFin, $turno, $aceptoTerminos);
        if (!empty($errors)) {
            Response::error('Datos de reserva inválidos.', 422, $errors);
        }

        $db = Database::getConnection();
        $stmtE = $db->prepare('SELECT * FROM especialidades WHERE clave = ? OR id = ? LIMIT 1');
        $stmtE->execute([$espInput, is_numeric($espInput) ? (int)$espInput : 0]);
        $esp = $stmtE->fetch();
        if (!$esp) {
            Response::error('La especialidad seleccionada no existe.', 422, ['especialidad' => 'Especialidad no válida']);
        }

        $medicoId = !empty($body['medico_id']) ? (int)$body['medico_id'] : 0;
        if ($medicoId <= 0) {
            $stmtM = $db->prepare('SELECT id FROM medicos WHERE especialidad_id = ? AND activo = 1 ORDER BY id ASC LIMIT 1');
            $stmtM->execute([$esp['id']]);
            $medicoId = (int)($stmtM->fetchColumn() ?: 1);
        }

        $db->beginTransaction();
        try {
            $stmtBlk = $db->prepare('SELECT motivo FROM medico_disponibilidad WHERE medico_id = ? AND fecha = ? AND estado IN ("bloqueado", "ausencia") AND (turno = "completo" OR turno = ? OR (? BETWEEN hora_inicio AND hora_fin)) FOR UPDATE');
            $stmtBlk->execute([$medicoId, $fechaInicio, $turno, $horaCita]);
            if ($bloqueo = $stmtBlk->fetch()) {
                $db->rollBack();
                Response::error('El horario seleccionado se encuentra bloqueado en la agenda del médico: ' . $bloqueo['motivo'], 409);
            }

            $stmtSolape = $db->prepare('SELECT id FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ? AND estado NOT IN ("cancelada") FOR UPDATE');
            $stmtSolape->execute([$medicoId, $fechaInicio, $horaCita]);
            if ($stmtSolape->fetch()) {
                $db->rollBack();
                Response::error('El médico ya cuenta con una cita programada en esa fecha y hora (Conflicto de horario 409). Por favor elija otro horario o turno.', 409);
            }

            $usuarioId = $user ? (int)$user['id'] : null;
            [$pacienteId, $nombrePaciente] = $this->resolveOrCreatePaciente($db, $user, $usuarioId, $dni, $telefono, $email, $body);

            $codigoCita = 'CIT-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $ins = $db->prepare('INSERT INTO citas (codigo_cita, paciente_id, usuario_id, dni_paciente, nombre_paciente, telefono_paciente, email_paciente, especialidad_id, medico_id, fecha_cita, fecha_fin_rango, hora_cita, turno, estado, motivo_consulta, notificacion_enviada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "programada", ?, 1)');
            $ins->execute([$codigoCita, $pacienteId, $usuarioId, $dni, $nombrePaciente, $telefono, $email, $esp['id'], $medicoId, $fechaInicio, $fechaFin ?: $fechaInicio, $horaCita, $turno, $motivo]);
            $citaId = (int)$db->lastInsertId();

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $stmtMName = $db->prepare('SELECT nombre FROM medicos WHERE id = ?');
        $stmtMName->execute([$medicoId]);
        $medicoNombre = (string)$stmtMName->fetchColumn();

        AuditService::queueNotification(
            "{$email} / WhatsApp {$telefono}",
            'ambos',
            "Confirmación de Cita {$codigoCita} - {$esp['nombre']}",
            "Estimado(a) {$nombrePaciente}, su cita {$codigoCita} en {$esp['nombre']} con {$medicoNombre} ha sido programada para el {$fechaInicio} a las " . substr($horaCita, 0, 5) . " (Turno {$turno}). Recuerde asistir 15 minutos antes (Tolerancia máxima: 15 min).",
            'cita',
            $citaId
        );
        AuditService::log($user, 'AGENDAR_CITA', 'citas', $codigoCita, "Cita agendada para DNI {$dni} con médico ID {$medicoId} el {$fechaInicio} {$horaCita}", 201);

        Response::json([
            'ok' => true,
            'message' => '¡Cita programada exitosamente! Se ha enviado la notificación por WhatsApp y correo electrónico.',
            'cita' => [
                'id' => $citaId,
                'codigo_cita' => $codigoCita,
                'dni_paciente' => $dni,
                'nombre_paciente' => $nombrePaciente,
                'especialidad' => $esp['nombre'],
                'medico' => $medicoNombre,
                'fecha_cita' => $fechaInicio,
                'hora_cita' => substr($horaCita, 0, 5),
                'turno' => $turno,
                'estado' => 'programada',
                'notificacion' => 'Enviada por WhatsApp y Email (<60s)',
            ],
        ], 201);
    }

    public function updateOrDelete(Request $req, int $citaId): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM citas WHERE id = ? LIMIT 1');
        $stmt->execute([$citaId]);
        $cita = $stmt->fetch();
        if (!$cita) {
            Response::error('La cita indicada no existe.', 404);
        }

        $horasRestantes = (strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) - time()) / 3600;

        if ($req->method === 'DELETE') {
            $this->cancelarCita($db, $user, $cita, $citaId, $horasRestantes);
        }

        $body = $req->body();
        if ((!empty($body['accion']) && $body['accion'] === 'reprogramar') || $user['rol'] === 'paciente') {
            $this->reprogramarCita($db, $user, $cita, $citaId, $body, $horasRestantes);
        }

        $this->actualizarEstadoClinico($db, $user, $cita, $citaId, $body);
    }

    public function derivar(Request $req, int $citaOrigenId): never
    {
        $user = JwtAuth::requireAuth(['medico', 'admin']);
        $db = Database::getConnection();
        $body = $req->body();

        $stmtOrig = $db->prepare('SELECT * FROM citas WHERE id = ? LIMIT 1');
        $stmtOrig->execute([$citaOrigenId]);
        $origen = $stmtOrig->fetch();
        if (!$origen) {
            Response::error('Cita de origen no encontrada.', 404);
        }

        $espDestinoId = (int)($body['especialidad_id'] ?? 0);
        $medicoDestinoId = (int)($body['medico_id'] ?? 0);
        $fechaDerivada = trim((string)($body['fecha_cita'] ?? date('Y-m-d', strtotime('+2 days'))));
        $horaDerivada = $this->normalizeTime((string)($body['hora_cita'] ?? '10:00:00'));
        $motivoDerivacion = trim((string)($body['motivo'] ?? 'Interconsulta médica especializada'));

        if ($espDestinoId <= 0 || $motivoDerivacion === '') {
            Response::error('Seleccione la especialidad destino e indique el motivo clínico de la derivación.', 422);
        }

        if ($medicoDestinoId <= 0) {
            $stmtM = $db->prepare('SELECT id FROM medicos WHERE especialidad_id = ? AND activo = 1 LIMIT 1');
            $stmtM->execute([$espDestinoId]);
            $medicoDestinoId = (int)($stmtM->fetchColumn() ?: 1);
        }

        $db->beginTransaction();
        try {
            $stmtBlk = $db->prepare('SELECT motivo FROM medico_disponibilidad WHERE medico_id = ? AND fecha = ? AND estado IN ("bloqueado", "ausencia") AND (turno = "completo" OR (? BETWEEN hora_inicio AND hora_fin)) FOR UPDATE');
            $stmtBlk->execute([$medicoDestinoId, $fechaDerivada, $horaDerivada]);
            if ($bloqueo = $stmtBlk->fetch()) {
                $db->rollBack();
                Response::error('El médico destino tiene bloqueado ese horario: ' . $bloqueo['motivo'], 409);
            }

            $stmtSolape = $db->prepare('SELECT id FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ? AND estado NOT IN ("cancelada") FOR UPDATE');
            $stmtSolape->execute([$medicoDestinoId, $fechaDerivada, $horaDerivada]);
            if ($stmtSolape->fetch()) {
                $db->rollBack();
                Response::error('El médico destino ya cuenta con una cita programada en esa fecha y hora (409).', 409);
            }

            $codigoDerivada = 'DER-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $ins = $db->prepare('INSERT INTO citas (codigo_cita, paciente_id, usuario_id, dni_paciente, nombre_paciente, telefono_paciente, email_paciente, especialidad_id, medico_id, fecha_cita, fecha_fin_rango, hora_cita, turno, estado, motivo_consulta, derivada_de_cita_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "manana", "programada", ?, ?)');
            $ins->execute([
                $codigoDerivada,
                $origen['paciente_id'],
                $origen['usuario_id'],
                $origen['dni_paciente'],
                $origen['nombre_paciente'],
                $origen['telefono_paciente'],
                $origen['email_paciente'],
                $espDestinoId,
                $medicoDestinoId,
                $fechaDerivada,
                $fechaDerivada,
                $horaDerivada,
                "Interconsulta derivada: {$motivoDerivacion}",
                $citaOrigenId,
            ]);
            $nuevaId = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        AuditService::queueNotification(
            $origen['email_paciente'],
            'ambos',
            "Interconsulta Derivada {$codigoDerivada}",
            "Se ha generado su cita de interconsulta {$codigoDerivada} para el {$fechaDerivada}. Motivo: {$motivoDerivacion}",
            'derivacion',
            $nuevaId
        );
        AuditService::log($user, 'DERIVAR_PACIENTE_INTERCONSULTA', 'citas', $codigoDerivada, "Derivación desde cita #{$citaOrigenId} a especialidad #{$espDestinoId}", 201);

        Response::json([
            'ok' => true,
            'message' => 'Interconsulta / derivación creada exitosamente.',
            'codigo_cita' => $codigoDerivada,
            'id' => $nuevaId,
        ], 201);
    }

    private function normalizeTime(string $raw): string
    {
        $hora = trim($raw);
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    /** @return array<string, string> */
    private function validateBooking(
        string $dni,
        string $telefono,
        string $email,
        string $espInput,
        string $fechaInicio,
        string $fechaFin,
        string $turno,
        bool $aceptoTerminos
    ): array {
        $errors = [];
        if (!preg_match('/^\d{8}$/', $dni)) $errors['dni'] = 'El DNI debe tener exactamente 8 dígitos.';
        if (!preg_match('/^\d{9}$/', $telefono)) $errors['numero_cel'] = 'El número de celular debe tener 9 dígitos.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Ingrese un correo electrónico válido.';
        if ($espInput === '') $errors['especialidad'] = 'Seleccione una especialidad.';
        if ($fechaInicio === '') {
            $errors['fecha_inicio'] = 'Seleccione la fecha de la cita.';
        } elseif ($fechaInicio < date('Y-m-d')) {
            $errors['fecha_inicio'] = 'No es posible agendar citas en fechas pasadas.';
        }
        if ($fechaFin !== '' && $fechaFin < $fechaInicio) $errors['fecha_fin'] = 'La fecha fin no puede ser menor a la fecha de inicio.';
        if (!in_array($turno, ['manana', 'tarde', 'ambos'], true)) $errors['turno'] = 'Seleccione un turno válido.';
        if (!$aceptoTerminos) $errors['terminos'] = 'Debe aceptar la Política de Privacidad y Términos.';
        return $errors;
    }

    /** @return array{0: int, 1: string} */
    private function resolveOrCreatePaciente(
        PDO $db,
        ?array $user,
        ?int $usuarioId,
        string $dni,
        string $telefono,
        string $email,
        array $body
    ): array {
        $stmtPac = $db->prepare('SELECT id, nombre, apellidos FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtPac->execute([$dni]);
        if ($pac = $stmtPac->fetch()) {
            return [(int)$pac['id'], "{$pac['nombre']} {$pac['apellidos']}"];
        }

        $nombrePaciente = trim((string)($body['nombre_paciente'] ?? ($user['nombre'] ?? "Paciente DNI {$dni}")));
        $pacUsuarioId = null;
        if ($usuarioId !== null) {
            $stmtU = $db->prepare('SELECT id FROM pacientes WHERE usuario_id = ? LIMIT 1');
            $stmtU->execute([$usuarioId]);
            if (!$stmtU->fetch()) {
                $pacUsuarioId = $usuarioId;
            }
        }

        $partes = preg_split('/\s+/', $nombrePaciente, 2);
        $pacNom = ($partes[0] ?? '') !== '' ? $partes[0] : 'Paciente';
        $pacApe = $partes[1] ?? "DNI {$dni}";

        $insPac = $db->prepare('INSERT INTO pacientes (usuario_id, dni, nombre, apellidos, edad, fecha_nacimiento, genero, telefono, email, direccion, departamento, provincia, distrito, nombre_emergencia, apellido_emergencia, parentesco_emergencia, telefono_emergencia, tipo_sangre) VALUES (?, ?, ?, ?, 30, "1996-01-01", "otro", ?, ?, "Pendiente de registro", "Lima", "Lima", "Independencia", "Familiar", "Emergencia", "Familiar", ?, "O+")');
        $insPac->execute([$pacUsuarioId, $dni, $pacNom, $pacApe, $telefono, $email, $telefono]);

        return [(int)$db->lastInsertId(), $nombrePaciente];
    }

    private function cancelarCita(PDO $db, array $user, array $cita, int $citaId, float $horasRestantes): never
    {
        if ($user['rol'] === 'paciente' && $horasRestantes < 24) {
            Response::error('No es posible cancelar la cita con menos de 24 horas de anticipación según nuestros Términos y Condiciones (Regla 24h).', 422);
        }
        $db->prepare('UPDATE citas SET estado = "cancelada" WHERE id = ?')->execute([$citaId]);
        AuditService::queueNotification($cita['email_paciente'], 'ambos', "Cancelación de Cita {$cita['codigo_cita']}", "Su cita {$cita['codigo_cita']} ha sido cancelada.", 'cita', $citaId);
        AuditService::log($user, 'CANCELAR_CITA', 'citas', $cita['codigo_cita'], 'Cita cancelada cumpliendo regla >24h', 200);
        Response::json(['ok' => true, 'message' => 'Cita cancelada correctamente.']);
    }

    private function reprogramarCita(PDO $db, array $user, array $cita, int $citaId, array $body, float $horasRestantes): never
    {
        if ($horasRestantes < 24 && $user['rol'] === 'paciente') {
            Response::error('Las reprogramaciones deben realizarse con al menos 24 horas de anticipación (Regla 24h TyC).', 422);
        }

        $nuevaFecha = trim((string)($body['fecha_cita'] ?? $cita['fecha_cita']));
        $nuevaHora = $this->normalizeTime((string)($body['hora_cita'] ?? $cita['hora_cita']));
        $nuevoTurno = trim((string)($body['turno'] ?? $cita['turno']));

        $stmtSolape = $db->prepare('SELECT id FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ? AND id != ? AND estado NOT IN ("cancelada")');
        $stmtSolape->execute([$cita['medico_id'], $nuevaFecha, $nuevaHora, $citaId]);
        if ($stmtSolape->fetch()) {
            Response::error('El nuevo horario elegido ya está ocupado para este médico.', 409);
        }

        $db->prepare('UPDATE citas SET fecha_cita = ?, hora_cita = ?, turno = ?, estado = "reprogramada" WHERE id = ?')
           ->execute([$nuevaFecha, $nuevaHora, $nuevoTurno, $citaId]);
        AuditService::queueNotification($cita['email_paciente'], 'ambos', "Reprogramación de Cita {$cita['codigo_cita']}", "Su cita {$cita['codigo_cita']} fue reprogramada para el {$nuevaFecha} a las " . substr($nuevaHora, 0, 5), 'cita', $citaId);
        AuditService::log($user, 'REPROGRAMAR_CITA', 'citas', $cita['codigo_cita'], "Cita reprogramada a {$nuevaFecha} {$nuevaHora}", 200);
        Response::json(['ok' => true, 'message' => 'Cita reprogramada exitosamente.']);
    }

    private function actualizarEstadoClinico(PDO $db, array $user, array $cita, int $citaId, array $body): never
    {
        if (!in_array($user['rol'], ['medico', 'admin'], true)) {
            Response::error('Solo médicos o administradores pueden cambiar el estado clínico de la cita.', 403);
        }

        $nuevoEstado = trim((string)($body['estado'] ?? ''));
        $observaciones = trim((string)($body['observaciones_atencion'] ?? $cita['observaciones_atencion'] ?? ''));
        if (!in_array($nuevoEstado, ['programada', 'en_atencion', 'finalizada', 'no_show', 'cancelada'], true)) {
            Response::error('Estado de cita no válido.', 422);
        }

        if ($nuevoEstado === 'finalizada') {
            $stmtHcCount = $db->prepare('SELECT COUNT(*) FROM historias_clinicas WHERE cita_id = ? OR dni_paciente = ?');
            $stmtHcCount->execute([$citaId, $cita['dni_paciente']]);
            if ((int)$stmtHcCount->fetchColumn() === 0 && $observaciones === '') {
                Response::error('Para finalizar la cita es obligatorio haber registrado la historia clínica o ingresar las observaciones médicas de cierre (RF-M03).', 422);
            }
            $db->prepare('UPDATE medicos SET consultas_realizadas = consultas_realizadas + 1 WHERE id = ?')->execute([$cita['medico_id']]);
        }

        $penalidad = 0.00;
        if ($nuevoEstado === 'no_show') {
            $penalidad = 20.00;
            $db->prepare('UPDATE pacientes SET penalidad_noshow = penalidad_noshow + 20.00, deuda_pendiente = deuda_pendiente + 20.00 WHERE dni = ?')
               ->execute([$cita['dni_paciente']]);
            if ($observaciones === '') {
                $observaciones = 'Inasistencia (No-Show) registrada tras cumplirse los 15 minutos de tolerancia. Cargo administrativo S/ 20.00 según TyC.';
            }
        }

        $db->prepare('UPDATE citas SET estado = ?, observaciones_atencion = ?, penalidad_aplicada = ? WHERE id = ?')
           ->execute([$nuevoEstado, $observaciones, $penalidad, $citaId]);
        AuditService::log($user, 'CAMBIO_ESTADO_CITA', 'citas', $cita['codigo_cita'], "Estado actualizado a {$nuevoEstado}", 200);
        Response::json(['ok' => true, 'message' => "Estado de la cita actualizado a {$nuevoEstado}."]);
    }
}
