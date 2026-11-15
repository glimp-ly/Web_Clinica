<?php
declare(strict_types=1);

date_default_timezone_set('America/Lima');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const JWT_SECRET = 'centenario_norte_jwt_secret_key_2026_ley29733';
const JWT_EXP_SECONDS = 86400;

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $envHost = getenv('DB_HOST') ?: '127.0.0.1';
    $envPort = getenv('DB_PORT') ?: '3306';
    $envName = getenv('DB_NAME') ?: 'test_clinica';
    $envUser = getenv('DB_USER') ?: '';
    $envPass = getenv('DB_PASS') ?: '';
    $envSocket = getenv('DB_SOCKET') ?: '/run/mysqld/mysqld.sock';

    $candidates = [];
    if (file_exists($envSocket)) {
        $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", $envUser, $envPass];
        $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", 'glimp', ''];
        $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", 'root', ''];
    }
    $candidates[] = ["mysql:host={$envHost};port={$envPort};dbname={$envName};charset=utf8mb4", $envUser, $envPass];
    $candidates[] = ["mysql:host=127.0.0.1;port=3306;dbname={$envName};charset=utf8mb4", '', ''];
    $candidates[] = ["mysql:host=127.0.0.1;port=3307;dbname={$envName};charset=utf8mb4", 'root', 'secret'];
    $candidates[] = ["mysql:host=172.17.0.1;port=3307;dbname={$envName};charset=utf8mb4", 'root', 'secret'];
    $candidates[] = ["mysql:host=172.17.0.1;port=3306;dbname={$envName};charset=utf8mb4", '', ''];
    $candidates[] = ["mysql:host=host.docker.internal;port=3307;dbname={$envName};charset=utf8mb4", 'root', 'secret'];
    $candidates[] = ["mysql:host=host.docker.internal;port=3306;dbname={$envName};charset=utf8mb4", '', ''];
    $candidates[] = ["mysql:host=red_integridad_db;port=3306;dbname={$envName};charset=utf8mb4", 'root', 'secret'];

    $lastException = null;
    foreach ($candidates as [$dsn, $user, $pass]) {
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error de conexión a la base de datos MariaDB',
        'detail' => $lastException ? $lastException->getMessage() : 'Sin conexión disponible'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, array $errors = []): never {
    $payload = ['ok' => false, 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    jsonResponse($payload, $status);
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST ?: [];
}

function base64UrlEncode(string $input): string {
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function base64UrlDecode(string $input): string {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($input, '-_', '+/')) ?: '';
}

function createJwt(array $user): string {
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'sub' => (int)$user['id'],
        'email' => $user['email'],
        'nombre' => $user['nombre'],
        'rol' => $user['rol'],
        'iat' => time(),
        'exp' => time() + JWT_EXP_SECONDS,
    ]));
    $signature = base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", JWT_SECRET, true));
    return "{$header}.{$payload}.{$signature}";
}

function verifyJwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header, $payload, $signature] = $parts;
    $expected = base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", JWT_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $data = json_decode(base64UrlDecode($payload), true);
    if (!is_array($data) || empty($data['exp']) || $data['exp'] < time()) {
        return null;
    }
    return $data;
}

function setAuthCookie(string $token): void {
    setcookie('centenario_token', $token, [
        'expires' => time() + JWT_EXP_SECONDS,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAuthCookie(): void {
    setcookie('centenario_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function getCurrentUser(): ?array {
    $token = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    } elseif (!empty($_COOKIE['centenario_token'])) {
        $token = $_COOKIE['centenario_token'];
    }
    if (!$token) {
        return null;
    }
    $claims = verifyJwt($token);
    if (!$claims) {
        return null;
    }
    $db = getDb();
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

function requireAuth(array $allowedRoles = []): array {
    $user = getCurrentUser();
    if (!$user) {
        logAudit(null, 'ACCESO_NO_AUTENTICADO', $_SERVER['REQUEST_URI'] ?? 'api', null, 'Token ausente o inválido', 401);
        jsonError('No autenticado. Por favor inicie sesión.', 401);
    }
    if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles, true)) {
        logAudit($user, 'ACCESO_DENEGADO_ROL', $_SERVER['REQUEST_URI'] ?? 'api', null, "Rol {$user['rol']} intentó acceder a ruta restringida", 403);
        jsonError('No tiene permisos suficientes para realizar esta acción (Rol requerido: ' . implode(', ', $allowedRoles) . ').', 403);
    }
    return $user;
}

function logAudit(?array $user, string $accion, string $recurso, ?string $recursoId, string $detalle, int $httpStatus = 200): void {
    try {
        $db = getDb();
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
            $httpStatus
        ]);
    } catch (Throwable $e) {
    }
}

function queueNotification(string $destinatario, string $canal, string $asunto, string $mensaje, string $tipo, ?int $refId = null): void {
    try {
        $db = getDb();
        $stmt = $db->prepare('INSERT INTO notificaciones (destinatario, canal, asunto, mensaje, referencia_tipo, referencia_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$destinatario, $canal, $asunto, $mensaje, $tipo, $refId, 'ENTREGADO']);
    } catch (Throwable $e) {
    }
}
