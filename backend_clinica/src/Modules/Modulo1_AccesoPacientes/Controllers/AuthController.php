<?php
declare(strict_types=1);

namespace App\Modules\Modulo1_AccesoPacientes\Controllers;

use App\Config\Database;
use App\Core\AuditService;
use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class AuthController
{
    public function register(Request $req): never
    {
        $db = Database::getConnection();
        $body = $req->body();
        $email = strtolower(trim((string)($body['email'] ?? $body['emailInput'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirmPassword'] ?? $password);
        $nombre = trim((string)($body['nombre'] ?? ''));

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ingrese un correo electrónico válido.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.';
        }
        if ($password !== $confirm) {
            $errors['confirmPassword'] = 'Las contraseñas no coinciden.';
        }
        if (!empty($errors)) {
            Response::error('Error de validación en el registro.', 422, $errors);
        }

        $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('El correo electrónico ya está registrado.', 409, ['email' => 'El correo ya existe.']);
        }

        if ($nombre === '') {
            $nombre = ucfirst(explode('@', $email)[0]);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol) VALUES (?, ?, ?, "paciente")');
        $ins->execute([$email, $hash, $nombre]);
        $userId = (int)$db->lastInsertId();

        $user = [
            'id' => $userId,
            'email' => $email,
            'nombre' => $nombre,
            'rol' => 'paciente',
        ];
        $token = JwtAuth::createToken($user);
        JwtAuth::setCookie($token);
        AuditService::log($user, 'REGISTRO_USUARIO', 'usuarios', (string)$userId, "Nuevo usuario registrado: {$email}", 201);

        Response::json([
            'ok' => true,
            'message' => 'Cuenta creada exitosamente.',
            'token' => $token,
            'user' => $user,
            'redirect' => 'formulario_datos.html',
        ], 201);
    }

    public function login(Request $req): never
    {
        $db = Database::getConnection();
        $body = $req->body();
        $email = strtolower(trim((string)($body['email'] ?? $body['emailInput'] ?? '')));
        $password = (string)($body['password'] ?? $body['passwordInput'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Response::error('Credenciales inválidas. Verifique su correo y contraseña.', 422, [
                'email' => 'Correo y contraseña requeridos.',
            ]);
        }

        $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();

        if (!$userRow) {
            AuditService::log(null, 'LOGIN_FALLIDO', 'usuarios', $email, 'Usuario no encontrado', 401);
            Response::error('Correo o contraseña incorrectos.', 401);
        }

        if (!empty($userRow['bloqueado_hasta']) && strtotime($userRow['bloqueado_hasta']) > time()) {
            $restante = ceil((strtotime($userRow['bloqueado_hasta']) - time()) / 60);
            AuditService::log($userRow, 'LOGIN_BLOQUEADO_RATELIMIT', 'usuarios', (string)$userRow['id'], 'Cuenta bloqueada temporalmente por intentos fallidos', 429);
            Response::error("Cuenta bloqueada temporalmente por seguridad (5 intentos fallidos). Intente en {$restante} min.", 429);
        }

        if (!password_verify($password, $userRow['password_hash'])) {
            $intentos = (int)$userRow['intentos_fallidos'] + 1;
            $bloqueadoHasta = ($intentos >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;
            $db->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?')
               ->execute([$intentos, $bloqueadoHasta, $userRow['id']]);
            AuditService::log($userRow, 'LOGIN_FALLIDO', 'usuarios', (string)$userRow['id'], "Intento fallido #{$intentos}", 401);
            if ($intentos >= 5) {
                Response::error('Ha superado los 5 intentos fallidos. Su cuenta ha sido bloqueada por 15 minutos.', 429);
            }
            Response::error('Correo o contraseña incorrectos.', 401);
        }

        $db->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$userRow['id']]);

        $user = [
            'id' => (int)$userRow['id'],
            'email' => $userRow['email'],
            'nombre' => $userRow['nombre'],
            'rol' => $userRow['rol'],
            'avatar_url' => $userRow['avatar_url'],
        ];
        $token = JwtAuth::createToken($user);
        JwtAuth::setCookie($token);
        AuditService::log($user, 'LOGIN_EXITOSO', 'usuarios', (string)$user['id'], "Inicio de sesión con rol {$user['rol']}", 200);

        Response::json([
            'ok' => true,
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'user' => $user,
            'redirect' => $this->resolveRedirect($db, $user),
        ]);
    }

    public function googleAccounts(): never
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT u.id, u.email, u.nombre, u.rol, u.google_id, m.especialidad_nombre, p.dni 
                            FROM usuarios u 
                            LEFT JOIN medicos m ON m.usuario_id = u.id 
                            LEFT JOIN pacientes p ON p.usuario_id = u.id 
                            WHERE u.activo = 1 ORDER BY FIELD(u.rol, "paciente", "medico", "admin"), u.id ASC');
        Response::json(['ok' => true, 'accounts' => $stmt->fetchAll()]);
    }

    public function googleLogin(Request $req): never
    {
        $db = Database::getConnection();
        $body = $req->body();
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $nombre = trim((string)($body['nombre'] ?? 'Usuario Google'));
        $googleId = trim((string)($body['google_id'] ?? ('google_' . md5($email))));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Correo de cuenta Google inválido.', 422);
        }

        $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? OR google_id = ? LIMIT 1');
        $stmt->execute([$email, $googleId]);
        $userRow = $stmt->fetch();

        if (!$userRow) {
            $randomHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT);
            $ins = $db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol, google_id) VALUES (?, ?, ?, "paciente", ?)');
            $ins->execute([$email, $randomHash, $nombre, $googleId]);
            $userRow = [
                'id' => (int)$db->lastInsertId(),
                'email' => $email,
                'nombre' => $nombre,
                'rol' => 'paciente',
                'avatar_url' => null,
            ];
        } elseif (empty($userRow['google_id'])) {
            $db->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')->execute([$googleId, $userRow['id']]);
        }

        $user = [
            'id' => (int)$userRow['id'],
            'email' => $userRow['email'],
            'nombre' => $userRow['nombre'],
            'rol' => $userRow['rol'],
            'avatar_url' => $userRow['avatar_url'] ?? null,
        ];
        $token = JwtAuth::createToken($user);
        JwtAuth::setCookie($token);
        AuditService::log($user, 'LOGIN_OAUTH_GOOGLE', 'usuarios', (string)$user['id'], "Autenticación OAuth Google ({$user['rol']})", 200);

        Response::json([
            'ok' => true,
            'message' => 'Autenticado con Google correctamente.',
            'token' => $token,
            'user' => $user,
            'redirect' => $this->resolveRedirect($db, $user),
        ]);
    }

    public function forgotPassword(Request $req): never
    {
        $db = Database::getConnection();
        $body = $req->body();
        $email = strtolower(trim((string)($body['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Ingrese un correo electrónico válido.', 422);
        }

        $stmt = $db->prepare('SELECT id, nombre FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        $resetToken = bin2hex(random_bytes(16));
        if ($u) {
            $expira = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE id = ?')->execute([$resetToken, $expira, $u['id']]);
            AuditService::queueNotification($email, 'email', 'Recuperación de contraseña - Clínica Centenario', "Su código de recuperación temporal (válido por 1 hora) es: {$resetToken}", 'auth', (int)$u['id']);
            AuditService::log(['id' => $u['id'], 'email' => $email, 'rol' => 'paciente'], 'SOLICITUD_RESET_PASSWORD', 'usuarios', (string)$u['id'], 'Token generado con expiración < 1h', 200);
        }

        Response::json([
            'ok' => true,
            'message' => 'Se ha enviado un token de recuperación a su correo (válido por 1 hora).',
            'demo_token' => $u ? $resetToken : null,
        ]);
    }

    public function resetPassword(Request $req): never
    {
        $db = Database::getConnection();
        $body = $req->body();
        $token = trim((string)($body['token'] ?? ''));
        $newPass = (string)($body['password'] ?? '');
        if ($token === '' || strlen($newPass) < 8 || !preg_match('/[A-Z]/', $newPass) || !preg_match('/\d/', $newPass)) {
            Response::error('Token y nueva contraseña segura (mínimo 8 caracteres, una mayúscula y un número) son obligatorios.', 422);
        }

        $stmt = $db->prepare('SELECT id, email, reset_expira FROM usuarios WHERE reset_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $u = $stmt->fetch();
        if (!$u || empty($u['reset_expira']) || strtotime($u['reset_expira']) < time()) {
            Response::error('El token de recuperación es inválido o ha expirado.', 422);
        }

        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE usuarios SET password_hash = ?, reset_token = NULL, reset_expira = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$hash, $u['id']]);
        AuditService::log(['id' => $u['id'], 'email' => $u['email'], 'rol' => 'paciente'], 'RESET_PASSWORD_COMPLETADO', 'usuarios', (string)$u['id'], 'Contraseña actualizada por recuperación', 200);
        Response::json(['ok' => true, 'message' => 'Contraseña actualizada correctamente. Ya puede iniciar sesión.']);
    }

    public function logout(): never
    {
        $user = JwtAuth::currentUser();
        if ($user) {
            AuditService::log($user, 'LOGOUT', 'usuarios', (string)$user['id'], 'Cierre de sesión', 200);
        }
        JwtAuth::clearCookie();
        Response::json(['ok' => true, 'message' => 'Sesión cerrada.']);
    }

    public function me(): never
    {
        $user = JwtAuth::currentUser();
        if (!$user) {
            Response::error('No hay sesión activa.', 401);
        }
        Response::json(['ok' => true, 'user' => $user]);
    }

    private function resolveRedirect(PDO $db, array $user): string
    {
        if ($user['rol'] === 'medico') {
            return 'medico.html';
        }
        if ($user['rol'] === 'admin') {
            return 'admin.html';
        }
        $stmt = $db->prepare('SELECT id FROM pacientes WHERE usuario_id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        return $stmt->fetch() ? 'mis_citas.html' : 'formulario_datos.html';
    }
}
