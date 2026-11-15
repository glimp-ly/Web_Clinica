<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use Throwable;

final class AuditService
{
    public static function log(?array $user, string $accion, string $recurso, ?string $recursoId, string $detalle, int $httpStatus = 200): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('INSERT INTO auditoria_logs (usuario_id, usuario_email, rol, accion, recurso, recurso_id, detalle, ip_address, http_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'] ?? null,
                $user['email'] ?? null,
                $user['rol'] ?? 'anonimo',
                $accion,
                $recurso,
                $recursoId,
                $detalle,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $httpStatus,
            ]);
        } catch (Throwable) {
        }
    }

    public static function queueNotification(string $destinatario, string $canal, string $asunto, string $mensaje, string $tipo, ?int $refId = null): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('INSERT INTO notificaciones (destinatario, canal, asunto, mensaje, referencia_tipo, referencia_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$destinatario, $canal, $asunto, $mensaje, $tipo, $refId, 'ENTREGADO']);
        } catch (Throwable) {
        }
    }
}
