<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

final class PerfilController
{
    public function show(): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();
        $data = ['user' => $user];

        if ($user['rol'] === 'paciente') {
            $stmt = $db->prepare('SELECT * FROM pacientes WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $data['ficha'] = $stmt->fetch() ?: null;
        } elseif ($user['rol'] === 'medico') {
            $stmt = $db->prepare('SELECT * FROM medicos WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $data['medico'] = $stmt->fetch() ?: null;
        }

        Response::json(['ok' => true, 'data' => $data]);
    }

    public function update(Request $req): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();
        $body = $req->body();

        if (isset($body['consentimiento_datos'])) {
            $consent = (int)((bool)$body['consentimiento_datos']);
            $db->prepare('UPDATE pacientes SET consentimiento_datos = ?, consentimiento_fecha = NOW() WHERE usuario_id = ?')
               ->execute([$consent, $user['id']]);
            AuditService::log($user, $consent ? 'OTORGAR_CONSENTIMIENTO' : 'REVOCAR_CONSENTIMIENTO', 'pacientes', (string)$user['id'], 'Cambio de consentimiento Ley 29733', 200);
        }
        if (!empty($body['nombre'])) {
            $db->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')
               ->execute([trim((string)$body['nombre']), $user['id']]);
        }

        Response::json(['ok' => true, 'message' => 'Preferencias y perfil actualizados correctamente.']);
    }

    public function anonymize(): never
    {
        $user = JwtAuth::requireAuth();
        $db = Database::getConnection();
        $anonHash = 'ANON-' . $user['id'] . '-' . substr(md5((string)time()), 0, 6);

        $db->prepare('UPDATE pacientes SET nombre = "ANONIMIZADO", apellidos = "LEY 29733", direccion = "RESERVADO", telefono = "000000000", telefono_alt = NULL, email = NULL, consentimiento_datos = 0, anonimizado = 1 WHERE usuario_id = ?')
           ->execute([$user['id']]);
        $db->prepare('UPDATE usuarios SET nombre = "Usuario Anonimizado", email = ?, activo = 0 WHERE id = ?')
           ->execute([$anonHash . '@anon.local', $user['id']]);

        AuditService::log($user, 'DERECHO_ARCO_CANCELACION_ANONIMIZACION', 'pacientes', (string)$user['id'], 'Datos anonimizados por solicitud ARCO del titular (Ley 29733)', 200);
        JwtAuth::clearCookie();
        Response::json(['ok' => true, 'message' => 'Sus datos personales han sido anonimizados conforme a la Ley N° 29733 (Derechos ARCO).']);
    }
}
