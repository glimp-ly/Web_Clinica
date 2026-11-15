<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Database;

final class JwtAuth
{
    private const SECRET = 'centenario_norte_jwt_secret_key_2026_ley29733';
    private const EXP_SECONDS = 86400;
    private const COOKIE_NAME = 'centenario_token';

    public static function createToken(array $user): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'sub' => (int)$user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'rol' => $user['rol'],
            'iat' => time(),
            'exp' => time() + self::EXP_SECONDS,
        ]));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", self::SECRET, true));
        return "{$header}.{$payload}.{$signature}";
    }

    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", self::SECRET, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($data) || empty($data['exp']) || $data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    public static function setCookie(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::EXP_SECONDS,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function currentUser(): ?array
    {
        $token = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (!empty($_COOKIE[self::COOKIE_NAME])) {
            $token = $_COOKIE[self::COOKIE_NAME];
        }
        if (!$token) {
            return null;
        }
        $claims = self::verifyToken($token);
        if (!$claims) {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, email, nombre, rol, google_id, avatar_url, activo FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$claims['sub']]);
        $user = $stmt->fetch();
        if (!$user || !(int)$user['activo']) {
            return null;
        }

        if ($user['rol'] === 'paciente') {
            $stmtP = $db->prepare('SELECT id, dni, nombre, apellidos, telefono, tiene_seguro, aseguradora, numero_poliza, cobertura_eps_pct, consentimiento_datos FROM pacientes WHERE usuario_id = ? LIMIT 1');
            $stmtP->execute([$user['id']]);
            $user['paciente'] = $stmtP->fetch() ?: null;
        } elseif ($user['rol'] === 'medico') {
            $stmtM = $db->prepare('SELECT id, cmp, dni, nombre, especialidad_id, especialidad_nombre, imagen FROM medicos WHERE usuario_id = ? LIMIT 1');
            $stmtM->execute([$user['id']]);
            $user['medico'] = $stmtM->fetch() ?: null;
        }

        return $user;
    }

    /**
     * @param list<string> $allowedRoles
     */
    public static function requireAuth(array $allowedRoles = []): array
    {
        $user = self::currentUser();
        if (!$user) {
            AuditService::log(null, 'ACCESO_NO_AUTENTICADO', $_SERVER['REQUEST_URI'] ?? 'api', null, 'Token ausente o inválido', 401);
            Response::error('No autenticado. Por favor inicie sesión.', 401);
        }
        if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles, true)) {
            AuditService::log($user, 'ACCESO_DENEGADO_ROL', $_SERVER['REQUEST_URI'] ?? 'api', null, "Rol {$user['rol']} intentó acceder a ruta restringida", 403);
            Response::error('No tiene permisos suficientes para realizar esta acción (Rol requerido: ' . implode(', ', $allowedRoles) . ').', 403);
        }
        return $user;
    }

    private static function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }
}
