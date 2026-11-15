<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

final class PacienteController
{
    public function index(Request $req): never
    {
        JwtAuth::requireAuth(['medico', 'admin']);
        $db = Database::getConnection();
        $search = trim((string)$req->query('search', ''));

        if ($search !== '') {
            $stmt = $db->prepare('SELECT * FROM pacientes WHERE dni LIKE ? OR nombre LIKE ? OR apellidos LIKE ? ORDER BY id DESC');
            $like = "%{$search}%";
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $db->query('SELECT * FROM pacientes ORDER BY id DESC');
        }
        Response::json(['ok' => true, 'pacientes' => $stmt->fetchAll()]);
    }

    public function store(Request $req): never
    {
        $user = JwtAuth::currentUser();
        $db = Database::getConnection();
        $body = $req->body();

        $nombre = trim((string)($body['nombre'] ?? ''));
        $apellidos = trim((string)($body['apellidos'] ?? ''));
        $dni = trim((string)($body['dni'] ?? ''));
        $edad = isset($body['edad']) ? (int)$body['edad'] : -1;
        $fechaNaci = trim((string)($body['fecha_nacimiento'] ?? $body['fecha-naci'] ?? ''));
        $genero = trim((string)($body['genero'] ?? ''));
        $pais = trim((string)($body['pais'] ?? 'Peru'));
        $telefono = trim((string)($body['telefono'] ?? ''));
        $telefonoAlt = trim((string)($body['telefono_alt'] ?? $body['telefono-alt'] ?? ''));
        $direccion = trim((string)($body['direccion'] ?? ''));
        $departamento = trim((string)($body['departamento'] ?? ''));
        $provincia = trim((string)($body['provincia'] ?? ''));
        $distrito = trim((string)($body['distrito'] ?? ''));
        $nombreEmer = trim((string)($body['nombre_emergencia'] ?? $body['nombre-emer'] ?? ''));
        $apellidoEmer = trim((string)($body['apellido_emergencia'] ?? $body['apellido-emer'] ?? ''));
        $parentesco = trim((string)($body['parentesco_emergencia'] ?? $body['parentesco'] ?? ''));
        $telEmer = trim((string)($body['telefono_emergencia'] ?? $body['telefono-emer'] ?? ''));
        $telAltEmer = trim((string)($body['telefono_alt_emergencia'] ?? $body['telefono-alt-emer'] ?? ''));
        $tipoSangre = trim((string)($body['tipo_sangre'] ?? $body['tipo-sangre'] ?? ''));

        $alergias = $this->normalizeListField($body['alergias'] ?? '');
        $enfermedades = $this->normalizeListField($body['enfermedades'] ?? $body['enfermedad'] ?? '');
        $cirugias = $this->normalizeListField($body['cirugias'] ?? '');

        $tieneSeguro = ($body['seguro'] ?? $body['tiene_seguro'] ?? 'no') === 'si' || !empty($body['tiene_seguro']) ? 1 : 0;
        $aseguradora = $tieneSeguro ? trim((string)($body['aseguradora'] ?? '')) : null;
        $poliza = $tieneSeguro ? trim((string)($body['numero_poliza'] ?? $body['poliza'] ?? '')) : null;

        $aceptoTerminos = !empty($body['terminos-condiciones']) || !empty($body['acepto_terminos']);
        $consentimiento = !empty($body['consentimiento']) || !empty($body['consentimiento_datos']);

        $errors = [];
        if (strlen($nombre) < 2) $errors['nombre'] = 'Ingrese su nombre completo.';
        if (strlen($apellidos) < 2) $errors['apellidos'] = 'Ingrese sus apellidos.';
        if (!preg_match('/^\d{8}$/', $dni)) $errors['dni'] = 'El DNI debe tener exactamente 8 dígitos numéricos.';
        if ($edad < 0 || $edad > 120) $errors['edad'] = 'Ingrese una edad válida (0-120).';
        if ($fechaNaci === '') $errors['fecha-naci'] = 'Ingrese su fecha de nacimiento.';
        if (!in_array($genero, ['m', 'f', 'otro'], true)) $errors['genero'] = 'Seleccione su género.';
        if (!preg_match('/^\d{9}$/', $telefono)) $errors['telefono'] = 'El teléfono principal debe tener 9 dígitos.';
        if ($telefonoAlt !== '' && !preg_match('/^\d{9}$/', $telefonoAlt)) $errors['telefono-alt'] = 'El teléfono alternativo debe tener 9 dígitos.';
        if ($telefonoAlt !== '' && $telefono === $telefonoAlt) $errors['telefono-alt'] = 'El teléfono alternativo debe ser distinto al principal.';
        if ($direccion === '') $errors['direccion'] = 'Ingrese su dirección domiciliaria.';
        if ($departamento === '' || $provincia === '' || $distrito === '') $errors['ubigeo'] = 'Seleccione departamento, provincia y distrito.';
        if ($nombreEmer === '' || $apellidoEmer === '' || $parentesco === '') $errors['emergencia'] = 'Complete los datos del contacto de emergencia.';
        if (!preg_match('/^\d{9}$/', $telEmer)) $errors['telefono-emer'] = 'El teléfono de emergencia debe tener 9 dígitos.';
        if (!in_array($tipoSangre, ['A+','A-','B+','B-','AB+','AB-','O+','O-'], true)) $errors['tipo-sangre'] = 'Seleccione un tipo de sangre válido.';
        if (!$aceptoTerminos || !$consentimiento) {
            $errors['consentimiento'] = 'Es obligatorio aceptar los Términos y Condiciones y el Consentimiento de Datos Personales (Ley 29733).';
        }

        if (!empty($errors)) {
            Response::error('Error de validación en la ficha del paciente.', 422, $errors);
        }

        $usuarioId = $user ? (int)$user['id'] : null;
        $email = $user['email'] ?? ($body['email'] ?? null);
        $cobertura = $tieneSeguro ? 75.00 : 0.00;

        $stmtExist = $db->prepare('SELECT id FROM pacientes WHERE dni = ? OR (usuario_id IS NOT NULL AND usuario_id = ?) LIMIT 1');
        $stmtExist->execute([$dni, $usuarioId]);
        $existing = $stmtExist->fetch();

        if ($existing) {
            $upd = $db->prepare('UPDATE pacientes SET usuario_id = COALESCE(usuario_id, ?), dni = ?, nombre = ?, apellidos = ?, edad = ?, fecha_nacimiento = ?, genero = ?, pais = ?, telefono = ?, telefono_alt = ?, email = COALESCE(?, email), direccion = ?, departamento = ?, provincia = ?, distrito = ?, nombre_emergencia = ?, apellido_emergencia = ?, parentesco_emergencia = ?, telefono_emergencia = ?, telefono_alt_emergencia = ?, tipo_sangre = ?, alergias = ?, enfermedades = ?, cirugias = ?, tiene_seguro = ?, aseguradora = ?, numero_poliza = ?, cobertura_eps_pct = ?, acepto_terminos = 1, consentimiento_datos = 1, consentimiento_fecha = NOW() WHERE id = ?');
            $upd->execute([$usuarioId, $dni, $nombre, $apellidos, $edad, $fechaNaci, $genero, $pais, $telefono, $telefonoAlt ?: null, $email, $direccion, $departamento, $provincia, $distrito, $nombreEmer, $apellidoEmer, $parentesco, $telEmer, $telAltEmer ?: null, $tipoSangre, $alergias, $enfermedades, $cirugias, $tieneSeguro, $aseguradora, $poliza, $cobertura, $existing['id']]);
            $pacienteId = (int)$existing['id'];
        } else {
            $ins = $db->prepare('INSERT INTO pacientes (usuario_id, dni, nombre, apellidos, edad, fecha_nacimiento, genero, pais, telefono, telefono_alt, email, direccion, departamento, provincia, distrito, nombre_emergencia, apellido_emergencia, parentesco_emergencia, telefono_emergencia, telefono_alt_emergencia, tipo_sangre, alergias, enfermedades, cirugias, tiene_seguro, aseguradora, numero_poliza, cobertura_eps_pct, acepto_terminos, consentimiento_datos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)');
            $ins->execute([$usuarioId, $dni, $nombre, $apellidos, $edad, $fechaNaci, $genero, $pais, $telefono, $telefonoAlt ?: null, $email, $direccion, $departamento, $provincia, $distrito, $nombreEmer, $apellidoEmer, $parentesco, $telEmer, $telAltEmer ?: null, $tipoSangre, $alergias, $enfermedades, $cirugias, $tieneSeguro, $aseguradora, $poliza, $cobertura]);
            $pacienteId = (int)$db->lastInsertId();
        }

        if ($usuarioId) {
            $db->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute(["{$nombre} {$apellidos}", $usuarioId]);
        }

        AuditService::log($user, 'GUARDAR_FICHA_PACIENTE', 'pacientes', $dni, "Ficha médica y consentimiento Ley 29733 registrados (ID: {$pacienteId})", 201);

        Response::json([
            'ok' => true,
            'message' => 'Ficha del paciente registrada correctamente.',
            'paciente_id' => $pacienteId,
            'dni' => $dni,
        ], 201);
    }

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

    private function normalizeListField(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map('trim', $value)));
        }
        return (string)$value;
    }
}
