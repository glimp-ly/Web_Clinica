<?php
declare(strict_types=1);

namespace App\Modules\Modulo1_AccesoPacientes\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

/**
 * Registro, actualización y consulta de fichas médicas de pacientes (Ley 29733 y EPS).
 */
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
        if (!in_array($tipoSangre, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) $errors['tipo-sangre'] = 'Seleccione un tipo de sangre válido.';
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

    private function normalizeListField(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map('trim', $value)));
        }
        return (string)$value;
    }
}
