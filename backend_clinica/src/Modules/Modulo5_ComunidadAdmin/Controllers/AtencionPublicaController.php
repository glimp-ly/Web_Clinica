<?php
declare(strict_types=1);

namespace App\Modules\Modulo5_ComunidadAdmin\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

/**
 * Preguntas frecuentes (FAQ) y solicitudes de contacto / afiliación de seguros.
 */
final class AtencionPublicaController
{
    public function listPreguntas(): never
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM preguntas_faq ORDER BY created_at DESC');
        Response::json(['ok' => true, 'preguntas' => $stmt->fetchAll()]);
    }

    public function storePregunta(Request $req): never
    {
        $body = $req->body();
        $nombre = trim((string)($body['nombre'] ?? $body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $pregunta = trim((string)($body['pregunta'] ?? $body['question'] ?? ''));

        $errors = [];
        if (strlen($nombre) < 3) $errors['name'] = 'Ingrese un nombre válido (mínimo 3 letras).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Correo electrónico inválido.';
        if (strlen($pregunta) < 10) $errors['question'] = 'La pregunta debe tener al menos 10 caracteres.';

        if (!empty($errors)) {
            Response::error('Error de validación al enviar la pregunta.', 422, $errors);
        }

        $db = Database::getConnection();
        $ins = $db->prepare('INSERT INTO preguntas_faq (nombre, email, pregunta, estado) VALUES (?, ?, ?, "pendiente")');
        $ins->execute([$nombre, $email, $pregunta]);

        Response::json([
            'ok' => true,
            'message' => 'Tu pregunta fue enviada correctamente y será respondida por nuestro equipo.',
            'id' => (int)$db->lastInsertId(),
        ], 201);
    }

    public function responderPregunta(Request $req, int $id): never
    {
        $user = JwtAuth::requireAuth(['admin']);
        $body = $req->body();
        $respuesta = trim((string)($body['respuesta'] ?? ''));
        if ($respuesta === '') {
            Response::error('La respuesta no puede estar vacía.', 422);
        }

        $db = Database::getConnection();
        $db->prepare('UPDATE preguntas_faq SET respuesta = ?, estado = "respondida", respondido_por = ?, respondido_at = NOW() WHERE id = ?')
           ->execute([$respuesta, $user['id'], $id]);

        AuditService::log($user, 'RESPONDER_PREGUNTA_FAQ', 'preguntas_faq', (string)$id, 'Pregunta FAQ respondida por admin', 200);
        Response::json(['ok' => true, 'message' => 'Respuesta publicada y enviada al usuario.']);
    }

    public function listContactos(): never
    {
        JwtAuth::requireAuth(['admin']);
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM contactos_afiliacion ORDER BY created_at DESC');
        Response::json(['ok' => true, 'contactos' => $stmt->fetchAll()]);
    }

    public function storeContacto(Request $req): never
    {
        $body = $req->body();
        $dni = trim((string)($body['dni'] ?? ''));
        $celular = trim((string)($body['celular'] ?? ''));
        $correo = strtolower(trim((string)($body['correo'] ?? $body['email'] ?? '')));
        $checkLegal = !empty($body['checkLegal']) || !empty($body['acepto_privacidad']);

        $errors = [];
        if (!preg_match('/^\d{8}$/', $dni)) $errors['dni'] = 'El DNI debe tener 8 dígitos.';
        if (!preg_match('/^\d{9}$/', $celular)) $errors['celular'] = 'El Celular debe tener 9 dígitos.';
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errors['correo'] = 'Ingrese un correo electrónico válido.';
        if (!$checkLegal) $errors['checkLegal'] = 'Debe aceptar la Política de Privacidad.';

        if (!empty($errors)) {
            Response::error('Datos de afiliación inválidos.', 422, $errors);
        }

        $db = Database::getConnection();
        $ins = $db->prepare('INSERT INTO contactos_afiliacion (dni, celular, correo, acepto_privacidad, estado) VALUES (?, ?, ?, 1, "ENVIADO")');
        $ins->execute([$dni, $celular, $correo ?: 'sin-correo@centenario.pe']);

        Response::json([
            'ok' => true,
            'message' => 'Solicitud de afiliación recibida en estado ENVIADO.',
            'estado' => 'ENVIADO',
            'id' => (int)$db->lastInsertId(),
        ], 201);
    }

    public function updateContacto(Request $req, int $id): never
    {
        JwtAuth::requireAuth(['admin']);
        $body = $req->body();
        $estado = in_array($body['estado'] ?? '', ['ENVIADO', 'EN_SEGUIMIENTO', 'AFILIADO', 'DESCARTADO'], true) ? $body['estado'] : 'EN_SEGUIMIENTO';
        $notas = trim((string)($body['notas_admin'] ?? ''));

        $db = Database::getConnection();
        $db->prepare('UPDATE contactos_afiliacion SET estado = ?, notas_admin = ? WHERE id = ?')->execute([$estado, $notas, $id]);

        Response::json(['ok' => true, 'message' => 'Estado de afiliación actualizado.']);
    }
}
