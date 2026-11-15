<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);

if (!empty($_GET['route'])) {
    $route = '/' . ltrim((string)$_GET['route'], '/');
} else {
    $pos = strpos($uri, '/api');
    $route = $pos !== false ? substr($uri, $pos + 4) : $uri;
    if ($route === '' || $route === '/index.php') {
        $route = '/';
    }
}
$route = rtrim($route, '/') ?: '/';
$segments = array_values(array_filter(explode('/', trim($route, '/')), 'strlen'));

$db = getDb();

// ============================================================
// 1. AUTHENTICATION & SOCIAL LOGIN (RF-C02, RF-C03, RF-M01, RF-B02, RNF-06)
// ============================================================

if ($route === '/auth/register' && $method === 'POST') {
    $body = getJsonBody();
    $email = strtolower(trim((string)($body['email'] ?? $body['emailInput'] ?? '')));
    $password = (string)($body['password'] ?? '');
    $confirm = (string)($body['confirmPassword'] ?? $password);
    $nombre = trim((string)($body['nombre'] ?? ''));

    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingrese un correo electrónico válido.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if ($password !== $confirm) {
        $errors['confirmPassword'] = 'Las contraseñas no coinciden.';
    }
    if (!empty($errors)) {
        jsonError('Error de validación en el registro.', 422, $errors);
    }

    $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonError('El correo electrónico ya está registrado.', 409, ['email' => 'El correo ya existe.']);
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
    $token = createJwt($user);
    setAuthCookie($token);
    logAudit($user, 'REGISTRO_USUARIO', 'usuarios', (string)$userId, "Nuevo usuario registrado: {$email}", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Cuenta creada exitosamente.',
        'token' => $token,
        'user' => $user,
        'redirect' => 'formulario_datos.html'
    ], 201);
}

if ($route === '/auth/login' && $method === 'POST') {
    $body = getJsonBody();
    $email = strtolower(trim((string)($body['email'] ?? $body['emailInput'] ?? '')));
    $password = (string)($body['password'] ?? $body['passwordInput'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        jsonError('Credenciales inválidas. Verifique su correo y contraseña.', 422, [
            'email' => 'Correo y contraseña requeridos.'
        ]);
    }

    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userRow = $stmt->fetch();

    if (!$userRow) {
        logAudit(null, 'LOGIN_FALLIDO', 'usuarios', $email, 'Usuario no encontrado', 401);
        jsonError('Correo o contraseña incorrectos.', 401);
    }

    if (!empty($userRow['bloqueado_hasta']) && strtotime($userRow['bloqueado_hasta']) > time()) {
        $restante = ceil((strtotime($userRow['bloqueado_hasta']) - time()) / 60);
        logAudit($userRow, 'LOGIN_BLOQUEADO_RATELIMIT', 'usuarios', (string)$userRow['id'], 'Cuenta bloqueada temporalmente por intentos fallidos', 429);
        jsonError("Cuenta bloqueada temporalmente por seguridad (5 intentos fallidos). Intente en {$restante} min.", 429);
    }

    if (!password_verify($password, $userRow['password_hash'])) {
        $intentos = (int)$userRow['intentos_fallidos'] + 1;
        $bloqueadoHasta = ($intentos >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;
        $upd = $db->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?');
        $upd->execute([$intentos, $bloqueadoHasta, $userRow['id']]);
        logAudit($userRow, 'LOGIN_FALLIDO', 'usuarios', (string)$userRow['id'], "Intento fallido #{$intentos}", 401);
        if ($intentos >= 5) {
            jsonError('Ha superado los 5 intentos fallidos. Su cuenta ha sido bloqueada por 15 minutos.', 429);
        }
        jsonError('Correo o contraseña incorrectos.', 401);
    }

    $db->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$userRow['id']]);

    $user = [
        'id' => (int)$userRow['id'],
        'email' => $userRow['email'],
        'nombre' => $userRow['nombre'],
        'rol' => $userRow['rol'],
        'avatar_url' => $userRow['avatar_url']
    ];
    $token = createJwt($user);
    setAuthCookie($token);
    logAudit($user, 'LOGIN_EXITOSO', 'usuarios', (string)$user['id'], "Inicio de sesión con rol {$user['rol']}", 200);

    $redirect = 'mis_citas.html';
    if ($user['rol'] === 'medico') {
        $redirect = 'medico.html';
    } elseif ($user['rol'] === 'admin') {
        $redirect = 'admin.html';
    } else {
        $stmtP = $db->prepare('SELECT id FROM pacientes WHERE usuario_id = ? LIMIT 1');
        $stmtP->execute([$user['id']]);
        if (!$stmtP->fetch()) {
            $redirect = 'formulario_datos.html';
        }
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Inicio de sesión exitoso.',
        'token' => $token,
        'user' => $user,
        'redirect' => $redirect
    ]);
}

if ($route === '/auth/google-accounts' && $method === 'GET') {
    $stmt = $db->query('SELECT u.id, u.email, u.nombre, u.rol, u.google_id, m.especialidad_nombre, p.dni 
                        FROM usuarios u 
                        LEFT JOIN medicos m ON m.usuario_id = u.id 
                        LEFT JOIN pacientes p ON p.usuario_id = u.id 
                        WHERE u.activo = 1 ORDER BY FIELD(u.rol, "paciente", "medico", "admin"), u.id ASC');
    jsonResponse(['ok' => true, 'accounts' => $stmt->fetchAll()]);
}

if ($route === '/auth/google' && $method === 'POST') {
    $body = getJsonBody();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $nombre = trim((string)($body['nombre'] ?? 'Usuario Google'));
    $googleId = trim((string)($body['google_id'] ?? ('google_' . md5($email))));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Correo de cuenta Google inválido.', 422);
    }

    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? OR google_id = ? LIMIT 1');
    $stmt->execute([$email, $googleId]);
    $userRow = $stmt->fetch();

    if (!$userRow) {
        $randomHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT);
        $ins = $db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol, google_id) VALUES (?, ?, ?, "paciente", ?)');
        $ins->execute([$email, $randomHash, $nombre, $googleId]);
        $userId = (int)$db->lastInsertId();
        $userRow = [
            'id' => $userId,
            'email' => $email,
            'nombre' => $nombre,
            'rol' => 'paciente',
            'avatar_url' => null
        ];
    } else {
        if (empty($userRow['google_id'])) {
            $db->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')->execute([$googleId, $userRow['id']]);
        }
    }

    $user = [
        'id' => (int)$userRow['id'],
        'email' => $userRow['email'],
        'nombre' => $userRow['nombre'],
        'rol' => $userRow['rol'],
        'avatar_url' => $userRow['avatar_url'] ?? null
    ];
    $token = createJwt($user);
    setAuthCookie($token);
    logAudit($user, 'LOGIN_OAUTH_GOOGLE', 'usuarios', (string)$user['id'], "Autenticación OAuth Google ({$user['rol']})", 200);

    $redirect = 'mis_citas.html';
    if ($user['rol'] === 'medico') {
        $redirect = 'medico.html';
    } elseif ($user['rol'] === 'admin') {
        $redirect = 'admin.html';
    } else {
        $stmtP = $db->prepare('SELECT id FROM pacientes WHERE usuario_id = ? LIMIT 1');
        $stmtP->execute([$user['id']]);
        if (!$stmtP->fetch()) {
            $redirect = 'formulario_datos.html';
        }
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Autenticado con Google correctamente.',
        'token' => $token,
        'user' => $user,
        'redirect' => $redirect
    ]);
}

if ($route === '/auth/forgot-password' && $method === 'POST') {
    $body = getJsonBody();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Ingrese un correo electrónico válido.', 422);
    }
    $stmt = $db->prepare('SELECT id, nombre FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    $resetToken = bin2hex(random_bytes(16));
    if ($u) {
        $expira = date('Y-m-d H:i:s', time() + 3600);
        $db->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE id = ?')->execute([$resetToken, $expira, $u['id']]);
        queueNotification($email, 'email', 'Recuperación de contraseña - Clínica Centenario', "Su código de recuperación temporal (válido por 1 hora) es: {$resetToken}", 'auth', (int)$u['id']);
        logAudit(['id' => $u['id'], 'email' => $email, 'rol' => 'paciente'], 'SOLICITUD_RESET_PASSWORD', 'usuarios', (string)$u['id'], 'Token generado con expiración < 1h', 200);
    }
    jsonResponse([
        'ok' => true,
        'message' => 'Se ha enviado un token de recuperación a su correo (válido por 1 hora).',
        'demo_token' => $u ? $resetToken : null
    ]);
}

if ($route === '/auth/reset-password' && $method === 'POST') {
    $body = getJsonBody();
    $token = trim((string)($body['token'] ?? ''));
    $newPass = (string)($body['password'] ?? '');
    if ($token === '' || strlen($newPass) < 6) {
        jsonError('Token y nueva contraseña (mínimo 6 caracteres) son obligatorios.', 422);
    }
    $stmt = $db->prepare('SELECT id, email, reset_expira FROM usuarios WHERE reset_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $u = $stmt->fetch();
    if (!$u || empty($u['reset_expira']) || strtotime($u['reset_expira']) < time()) {
        jsonError('El token de recuperación es inválido o ha expirado.', 422);
    }
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare('UPDATE usuarios SET password_hash = ?, reset_token = NULL, reset_expira = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$hash, $u['id']]);
    logAudit(['id' => $u['id'], 'email' => $u['email'], 'rol' => 'paciente'], 'RESET_PASSWORD_COMPLETADO', 'usuarios', (string)$u['id'], 'Contraseña actualizada por recuperación', 200);
    jsonResponse(['ok' => true, 'message' => 'Contraseña actualizada correctamente. Ya puede iniciar sesión.']);
}

if ($route === '/auth/logout' && $method === 'POST') {
    $user = getCurrentUser();
    if ($user) {
        logAudit($user, 'LOGOUT', 'usuarios', (string)$user['id'], 'Cierre de sesión', 200);
    }
    clearAuthCookie();
    jsonResponse(['ok' => true, 'message' => 'Sesión cerrada.']);
}

if ($route === '/auth/me' && $method === 'GET') {
    $user = getCurrentUser();
    if (!$user) {
        jsonError('No hay sesión activa.', 401);
    }
    jsonResponse(['ok' => true, 'user' => $user]);
}

// ============================================================
// 2. PERFIL, FICHA DE PACIENTE Y PRIVACIDAD ARCO (RF-C04, RF-C15, RF-B06, RNF-07)
// ============================================================

if ($route === '/perfil' && $method === 'GET') {
    $user = requireAuth();
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
    jsonResponse(['ok' => true, 'data' => $data]);
}

if ($route === '/perfil' && $method === 'PUT') {
    $user = requireAuth();
    $body = getJsonBody();
    if (isset($body['consentimiento_datos'])) {
        $consent = (int)((bool)$body['consentimiento_datos']);
        $db->prepare('UPDATE pacientes SET consentimiento_datos = ?, consentimiento_fecha = NOW() WHERE usuario_id = ?')->execute([$consent, $user['id']]);
        logAudit($user, $consent ? 'OTORGAR_CONSENTIMIENTO' : 'REVOCAR_CONSENTIMIENTO', 'pacientes', (string)$user['id'], 'Cambio de consentimiento Ley 29733', 200);
    }
    if (!empty($body['nombre'])) {
        $db->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute([trim((string)$body['nombre']), $user['id']]);
    }
    jsonResponse(['ok' => true, 'message' => 'Preferencias y perfil actualizados correctamente.']);
}

if ($route === '/perfil' && $method === 'DELETE') {
    $user = requireAuth();
    $anonHash = 'ANON-' . $user['id'] . '-' . substr(md5((string)time()), 0, 6);
    $db->prepare('UPDATE pacientes SET nombre = "ANONIMIZADO", apellidos = "LEY 29733", direccion = "RESERVADO", telefono = "000000000", telefono_alt = NULL, email = NULL, consentimiento_datos = 0, anonimizado = 1 WHERE usuario_id = ?')->execute([$user['id']]);
    $db->prepare('UPDATE usuarios SET nombre = "Usuario Anonimizado", email = ?, activo = 0 WHERE id = ?')->execute([$anonHash . '@anon.local', $user['id']]);
    logAudit($user, 'DERECHO_ARCO_CANCELACION_ANONIMIZACION', 'pacientes', (string)$user['id'], 'Datos anonimizados por solicitud ARCO del titular (Ley 29733)', 200);
    clearAuthCookie();
    jsonResponse(['ok' => true, 'message' => 'Sus datos personales han sido anonimizados conforme a la Ley N° 29733 (Derechos ARCO).']);
}

if ($route === '/pacientes' && $method === 'GET') {
    $user = requireAuth(['medico', 'admin']);
    $search = trim((string)($_GET['search'] ?? ''));
    if ($search !== '') {
        $stmt = $db->prepare('SELECT * FROM pacientes WHERE dni LIKE ? OR nombre LIKE ? OR apellidos LIKE ? ORDER BY id DESC');
        $like = "%{$search}%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $db->query('SELECT * FROM pacientes ORDER BY id DESC');
    }
    jsonResponse(['ok' => true, 'pacientes' => $stmt->fetchAll()]);
}

if ($route === '/pacientes' && $method === 'POST') {
    $user = getCurrentUser();
    $body = getJsonBody();

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

    $alergias = $body['alergias'] ?? '';
    if (is_array($alergias)) {
        $alergias = implode(', ', array_filter(array_map('trim', $alergias)));
    }
    $enfermedades = $body['enfermedades'] ?? $body['enfermedad'] ?? '';
    if (is_array($enfermedades)) {
        $enfermedades = implode(', ', array_filter(array_map('trim', $enfermedades)));
    }
    $cirugias = $body['cirugias'] ?? '';
    if (is_array($cirugias)) {
        $cirugias = implode(', ', array_filter(array_map('trim', $cirugias)));
    }

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
        jsonError('Error de validación en la ficha del paciente.', 422, $errors);
    }

    $usuarioId = $user ? (int)$user['id'] : null;
    $email = $user['email'] ?? ($body['email'] ?? null);
    $cobertura = $tieneSeguro ? 75.00 : 0.00;

    $stmtExist = $db->prepare('SELECT id FROM pacientes WHERE dni = ? OR (usuario_id IS NOT NULL AND usuario_id = ?) LIMIT 1');
    $stmtExist->execute([$dni, $usuarioId]);
    $existing = $stmtExist->fetch();

    if ($existing) {
        $upd = $db->prepare('UPDATE pacientes SET usuario_id = COALESCE(usuario_id, ?), dni = ?, nombre = ?, apellidos = ?, edad = ?, fecha_nacimiento = ?, genero = ?, pais = ?, telefono = ?, telefono_alt = ?, email = COALESCE(?, email), direccion = ?, departamento = ?, provincia = ?, distrito = ?, nombre_emergencia = ?, apellido_emergencia = ?, parentesco_emergencia = ?, telefono_emergencia = ?, telefono_alt_emergencia = ?, tipo_sangre = ?, alergias = ?, enfermedades = ?, cirugias = ?, tiene_seguro = ?, aseguradora = ?, numero_poliza = ?, cobertura_eps_pct = ?, acepto_terminos = 1, consentimiento_datos = 1, consentimiento_fecha = NOW() WHERE id = ?');
        $upd->execute([$usuarioId, $dni, $nombre, $apellidos, $edad, $fechaNaci, $genero, $pais, $telefono, $telefonoAlt ?: null, $email, $direccion, $departamento, $provincia, $distrito, $nombreEmer, $apellidoEmer, $parentesco, $telEmer, $telAltEmer ?: null, $tipoSangre, (string)$alergias, (string)$enfermedades, (string)$cirugias, $tieneSeguro, $aseguradora, $poliza, $cobertura, $existing['id']]);
        $pacienteId = (int)$existing['id'];
    } else {
        $ins = $db->prepare('INSERT INTO pacientes (usuario_id, dni, nombre, apellidos, edad, fecha_nacimiento, genero, pais, telefono, telefono_alt, email, direccion, departamento, provincia, distrito, nombre_emergencia, apellido_emergencia, parentesco_emergencia, telefono_emergencia, telefono_alt_emergencia, tipo_sangre, alergias, enfermedades, cirugias, tiene_seguro, aseguradora, numero_poliza, cobertura_eps_pct, acepto_terminos, consentimiento_datos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)');
        $ins->execute([$usuarioId, $dni, $nombre, $apellidos, $edad, $fechaNaci, $genero, $pais, $telefono, $telefonoAlt ?: null, $email, $direccion, $departamento, $provincia, $distrito, $nombreEmer, $apellidoEmer, $parentesco, $telEmer, $telAltEmer ?: null, $tipoSangre, (string)$alergias, (string)$enfermedades, (string)$cirugias, $tieneSeguro, $aseguradora, $poliza, $cobertura]);
        $pacienteId = (int)$db->lastInsertId();
    }

    if ($usuarioId) {
        $db->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute(["{$nombre} {$apellidos}", $usuarioId]);
    }

    logAudit($user, 'GUARDAR_FICHA_PACIENTE', 'pacientes', $dni, "Ficha médica y consentimiento Ley 29733 registrados (ID: {$pacienteId})", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Ficha del paciente registrada correctamente.',
        'paciente_id' => $pacienteId,
        'dni' => $dni
    ], 201);
}

// ============================================================
// 3. HISTORIA CLÍNICA POR PACIENTE (RF-M04, RF-B06)
// ============================================================

if (count($segments) === 3 && $segments[0] === 'pacientes' && $segments[2] === 'historia') {
    $dni = $segments[1];
    $user = requireAuth(['medico', 'admin', 'paciente']);

    $stmtP = $db->prepare('SELECT * FROM pacientes WHERE dni = ? LIMIT 1');
    $stmtP->execute([$dni]);
    $paciente = $stmtP->fetch();
    if (!$paciente) {
        jsonError('Paciente con DNI ' . $dni . ' no encontrado.', 404);
    }

    // Regla RF-M04: Solo médico asignado/tratante, el propio paciente (en lectura) o admin
    if ($user['rol'] === 'paciente') {
        if ($method !== 'GET' || (int)($paciente['usuario_id'] ?? 0) !== (int)$user['id']) {
            logAudit($user, 'ACCESO_DENEGADO_HISTORIA', 'historias_clinicas', $dni, 'Paciente intentó acceder a historia ajena', 403);
            jsonError('No autorizado para ver la historia clínica de otro paciente.', 403);
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
            logAudit($user, 'ACCESO_DENEGADO_HISTORIA', 'historias_clinicas', $dni, "Médico ID {$medicoId} no es médico tratante del DNI {$dni}", 403);
            jsonError('Acceso denegado (RF-M04): Solo el médico tratante asignado al paciente o el administrador pueden acceder a esta historia clínica.', 403);
        }
    }

    if ($method === 'GET') {
        $stmtH = $db->prepare('SELECT h.*, m.nombre AS medico_nombre, m.especialidad_nombre, m.cmp 
                               FROM historias_clinicas h 
                               JOIN medicos m ON m.id = h.medico_id 
                               WHERE h.dni_paciente = ? 
                               ORDER BY h.created_at DESC');
        $stmtH->execute([$dni]);
        $entradas = $stmtH->fetchAll();

        $stmtRec = $db->prepare('SELECT r.*, m.nombre AS medico_nombre FROM recetas r JOIN medicos m ON m.id = r.medico_id WHERE r.dni_paciente = ? ORDER BY r.created_at DESC');
        $stmtRec->execute([$dni]);

        $stmtLab = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE dni_paciente = ? ORDER BY created_at DESC');
        $stmtLab->execute([$dni]);

        logAudit($user, 'LECTURA_HISTORIA_CLINICA', 'historias_clinicas', $dni, "Visualización autorizada de historia clínica DNI {$dni}", 200);

        jsonResponse([
            'ok' => true,
            'paciente' => $paciente,
            'entradas' => $entradas,
            'recetas' => $stmtRec->fetchAll(),
            'ordenes_lab' => $stmtLab->fetchAll()
        ]);
    }

    if ($method === 'POST') {
        if (!in_array($user['rol'], ['medico', 'admin'], true)) {
            jsonError('Solo médicos tratantes pueden registrar evoluciones en la historia clínica.', 403);
        }
        $body = getJsonBody();
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
            jsonError('Motivo, anamnesis, diagnóstico y tratamiento son obligatorios.', 422);
        }

        $medicoId = (int)($user['medico']['id'] ?? $body['medico_id'] ?? 1);
        $ins = $db->prepare('INSERT INTO historias_clinicas (paciente_id, dni_paciente, cita_id, medico_id, motivo, anamnesis, examen_fisico, signos_vitales, diagnostico_cie10, diagnostico, tratamiento, evolucion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$paciente['id'], $dni, $citaId, $medicoId, $motivo, $anamnesis, $examenFisico, $signosVitales, $cie10, $diagnostico, $tratamiento, $evolucion]);
        $hcId = (int)$db->lastInsertId();

        if (!empty($body['alergias']) || !empty($body['enfermedades'])) {
            $db->prepare('UPDATE pacientes SET alergias = COALESCE(?, alergias), enfermedades = COALESCE(?, enfermedades) WHERE id = ?')
               ->execute([$body['alergias'] ?? null, $body['enfermedades'] ?? null, $paciente['id']]);
        }

        logAudit($user, 'ESCRITURA_HISTORIA_CLINICA', 'historias_clinicas', (string)$hcId, "Registro clínico CIE-10 {$cie10} para paciente DNI {$dni}", 201);

        jsonResponse([
            'ok' => true,
            'message' => 'Evolución registrada en la historia clínica y auditada.',
            'historia_id' => $hcId
        ], 201);
    }
}

// ============================================================
// 4. ESPECIALIDADES Y STAFF MÉDICO + DISPONIBILIDAD (RF-C08, RF-M02, RF-M08, RF-B07)
// ============================================================

if ($route === '/especialidades' && $method === 'GET') {
    $stmt = $db->query('SELECT * FROM especialidades WHERE activo = 1 ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['items'] = json_decode($r['servicios_json'], true) ?: [];
    }
    jsonResponse(['ok' => true, 'especialidades' => $rows]);
}

if ($route === '/medicos/mi-resumen' && $method === 'GET') {
    $user = requireAuth(['medico', 'admin']);
    $medicoId = (int)($user['medico']['id'] ?? $_GET['medico_id'] ?? 1);

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

    jsonResponse([
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
            'horas_acumuladas' => $horasAtendidas
        ]
    ]);
}

if ($route === '/medicos/mi-disponibilidad' && ($method === 'GET' || $method === 'PUT' || $method === 'POST')) {
    $user = requireAuth(['medico', 'admin']);
    $medicoId = (int)($user['medico']['id'] ?? $_GET['medico_id'] ?? 1);

    if ($method === 'GET') {
        $stmt = $db->prepare('SELECT * FROM medico_disponibilidad WHERE medico_id = ? ORDER BY fecha ASC, hora_inicio ASC');
        $stmt->execute([$medicoId]);
        jsonResponse(['ok' => true, 'disponibilidad' => $stmt->fetchAll()]);
    }

    $body = getJsonBody();
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

        $ins = $db->prepare('INSERT INTO medico_disponibilidad (medico_id, fecha, turno, hora_inicio, hora_fin, estado, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$medicoId, $fecha, $turno, $horaIni, $horaFin, $estado, $motivo]);
    }

    logAudit($user, 'ACTUALIZAR_DISPONIBILIDAD_MEDICO', 'medico_disponibilidad', (string)$medicoId, 'Actualización de agenda y disponibilidad médica', 200);
    jsonResponse(['ok' => true, 'message' => 'Disponibilidad actualizada correctamente.']);
}

if (count($segments) === 3 && $segments[0] === 'medicos' && $segments[1] === 'mi-disponibilidad' && $method === 'DELETE') {
    $user = requireAuth(['medico', 'admin']);
    $dispId = (int)$segments[2];
    $db->prepare('DELETE FROM medico_disponibilidad WHERE id = ?')->execute([$dispId]);
    jsonResponse(['ok' => true, 'message' => 'Bloque eliminado.']);
}

if ($route === '/medicos' && $method === 'GET') {
    $search = trim((string)($_GET['search'] ?? ''));
    $esp = trim((string)($_GET['especialidad'] ?? ''));
    $user = getCurrentUser();
    $showAll = ($user && $user['rol'] === 'admin' && !empty($_GET['all']));

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
    jsonResponse(['ok' => true, 'medicos' => $stmt->fetchAll()]);
}

if ($route === '/medicos' && $method === 'POST') {
    $user = requireAuth(['admin']);
    $body = getJsonBody();
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
        jsonError('Nombre, CMP y DNI (8 dígitos) son obligatorios.', 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('El correo institucional del médico es inválido.', 422);
    }

    $stmtDup = $db->prepare('SELECT id FROM medicos WHERE cmp = ? OR dni = ? LIMIT 1');
    $stmtDup->execute([$cmp, $dni]);
    if ($stmtDup->fetch()) {
        jsonError('Ya existe un médico registrado con ese CMP o DNI.', 409);
    }

    if ($email !== '') {
        $stmtEmail = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmtEmail->execute([$email]);
        if ($stmtEmail->fetch()) {
            jsonError('El correo electrónico ya está registrado en el sistema.', 409);
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
            $insU = $db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol) VALUES (?, ?, ?, "medico")');
            $insU->execute([$email, $hash, $nombre]);
            $usuarioId = (int)$db->lastInsertId();
        }
        $ins = $db->prepare('INSERT INTO medicos (usuario_id, cmp, dni, nombre, especialidad_id, especialidad_nombre, descripcion, imagen, experiencia_anios, anios_clinica, telefono, turno_manana, turno_tarde, dias_atencion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$usuarioId, $cmp, $dni, $nombre, $espId, $espNombre, $descripcion, $imagen, $exp, $aniosClinica, $telefono, $turnoM, $turnoT, $diasAt]);
        $id = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    logAudit($user, 'CREAR_MEDICO', 'medicos', (string)$id, "Médico creado: {$nombre} ({$cmp})", 201);
    jsonResponse(['ok' => true, 'message' => 'Médico registrado en el staff.', 'id' => $id], 201);
}

if (count($segments) === 2 && $segments[0] === 'medicos' && is_numeric($segments[1]) && ($method === 'PUT' || $method === 'DELETE')) {
    $user = requireAuth(['admin']);
    $id = (int)$segments[1];
    if ($method === 'DELETE') {
        $db->prepare('UPDATE medicos SET activo = 0 WHERE id = ?')->execute([$id]);
        logAudit($user, 'DESACTIVAR_MEDICO', 'medicos', (string)$id, 'Médico desactivado por administrador', 200);
        jsonResponse(['ok' => true, 'message' => 'Médico desactivado del catálogo.']);
    }
    $body = getJsonBody();
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
        $id
    ]);
    logAudit($user, 'ACTUALIZAR_MEDICO', 'medicos', (string)$id, 'Datos/horarios de médico actualizados', 200);
    jsonResponse(['ok' => true, 'message' => 'Datos del médico actualizados.']);
}

// ============================================================
// 5. CITAS MÉDICAS, REGLA 24H Y DERIVACIONES (RF-C05, RF-C06, RF-C07, RF-M03, RF-M07, RF-B04)
// ============================================================

if ($route === '/citas/ocupadas' && $method === 'GET') {
    $medicoId = (int)($_GET['medico_id'] ?? 0);
    $fecha = trim((string)($_GET['fecha'] ?? date('Y-m-d')));
    if ($medicoId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        jsonError('Debe indicar medico_id y fecha válidos.', 422);
    }
    $stmtH = $db->prepare('SELECT TIME_FORMAT(hora_cita, "%H:%i") AS hora FROM citas WHERE medico_id = ? AND fecha_cita = ? AND estado NOT IN ("cancelada")');
    $stmtH->execute([$medicoId, $fecha]);
    $horasOcupadas = $stmtH->fetchAll(PDO::FETCH_COLUMN);

    $stmtB = $db->prepare('SELECT turno, TIME_FORMAT(hora_inicio, "%H:%i") AS hora_inicio, TIME_FORMAT(hora_fin, "%H:%i") AS hora_fin, estado, motivo FROM medico_disponibilidad WHERE medico_id = ? AND fecha = ? AND estado IN ("bloqueado", "ausencia")');
    $stmtB->execute([$medicoId, $fecha]);
    jsonResponse(['ok' => true, 'horas_ocupadas' => $horasOcupadas, 'bloqueos' => $stmtB->fetchAll()]);
}

if (($route === '/citas' || $route === '/mis-citas') && $method === 'GET') {
    $user = getCurrentUser();
    $dniFilter = trim((string)($_GET['dni'] ?? ''));

    $sql = 'SELECT c.*, e.nombre AS especialidad_nombre, e.precio_consulta, m.nombre AS medico_nombre, m.cmp AS medico_cmp
            FROM citas c
            JOIN especialidades e ON e.id = c.especialidad_id
            JOIN medicos m ON m.id = c.medico_id';
    $params = [];

    if ($route === '/mis-citas' || ($user && $user['rol'] === 'paciente')) {
        if (!$user && $dniFilter === '') {
            jsonError('Inicie sesión o indique su DNI para consultar sus citas.', 401);
        }
        $pacienteDni = $user['paciente']['dni'] ?? $dniFilter;
        $userId = $user['id'] ?? -1;
        $sql .= ' WHERE (c.usuario_id = ? OR c.dni_paciente = ?)';
        $params[] = $userId;
        $params[] = $pacienteDni;
    } elseif ($user && $user['rol'] === 'medico') {
        $medicoId = (int)($user['medico']['id'] ?? 0);
        $sql .= ' WHERE c.medico_id = ?';
        $params[] = $medicoId;
    } elseif ($dniFilter !== '') {
        $sql .= ' WHERE c.dni_paciente = ?';
        $params[] = $dniFilter;
    }

    $sql .= ' ORDER BY c.fecha_cita DESC, c.hora_cita ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'citas' => $stmt->fetchAll()]);
}

if ($route === '/citas' && $method === 'POST') {
    $user = getCurrentUser();
    $body = getJsonBody();

    $dni = trim((string)($body['dni'] ?? ''));
    $telefono = trim((string)($body['numero_cel'] ?? $body['telefono'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $espInput = trim((string)($body['especialidad'] ?? $body['especialidad_id'] ?? ''));
    $fechaInicio = trim((string)($body['fecha_inicio'] ?? $body['fecha_cita'] ?? $body['fecha'] ?? ''));
    $fechaFin = trim((string)($body['fecha_fin'] ?? $fechaInicio));
    $turno = trim((string)($body['turno'] ?? 'manana'));
    $horaCita = trim((string)($body['hora_cita'] ?? ($turno === 'tarde' ? '15:00:00' : '09:00:00')));
    if (strlen($horaCita) === 5) {
        $horaCita .= ':00';
    }
    $motivo = trim((string)($body['motivo_consulta'] ?? 'Consulta médica especializada'));
    $aceptoTerminos = !empty($body['terminos-condiciones']) || !empty($body['acepto_terminos']);

    $errors = [];
    if (!preg_match('/^\d{8}$/', $dni)) $errors['dni'] = 'El DNI debe tener exactamente 8 dígitos.';
    if (!preg_match('/^\d{9}$/', $telefono)) $errors['numero_cel'] = 'El número de celular debe tener 9 dígitos.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Ingrese un correo electrónico válido.';
    if ($espInput === '') $errors['especialidad'] = 'Seleccione una especialidad.';
    if ($fechaInicio === '') $errors['fecha_inicio'] = 'Seleccione la fecha de la cita.';
    if ($fechaFin !== '' && $fechaFin < $fechaInicio) $errors['fecha_fin'] = 'La fecha fin no puede ser menor a la fecha de inicio.';
    if (!in_array($turno, ['manana', 'tarde', 'ambos'], true)) $errors['turno'] = 'Seleccione un turno válido.';
    if (!$aceptoTerminos) $errors['terminos'] = 'Debe aceptar la Política de Privacidad y Términos.';

    if (!empty($errors)) {
        jsonError('Datos de reserva inválidos.', 422, $errors);
    }

    $stmtE = $db->prepare('SELECT * FROM especialidades WHERE clave = ? OR id = ? LIMIT 1');
    $stmtE->execute([$espInput, is_numeric($espInput) ? (int)$espInput : 0]);
    $esp = $stmtE->fetch();
    if (!$esp) {
        jsonError('La especialidad seleccionada no existe.', 422, ['especialidad' => 'Especialidad no válida']);
    }

    $medicoId = !empty($body['medico_id']) ? (int)$body['medico_id'] : 0;
    if ($medicoId <= 0) {
        $stmtM = $db->prepare('SELECT id FROM medicos WHERE especialidad_id = ? AND activo = 1 ORDER BY id ASC LIMIT 1');
        $stmtM->execute([$esp['id']]);
        $medicoId = (int)($stmtM->fetchColumn() ?: 1);
    }

    $db->beginTransaction();
    try {
        // Verificar bloqueos o ausencias del médico (RF-M02)
        $stmtBlk = $db->prepare('SELECT motivo FROM medico_disponibilidad WHERE medico_id = ? AND fecha = ? AND estado IN ("bloqueado", "ausencia") AND (turno = "completo" OR turno = ? OR (? BETWEEN hora_inicio AND hora_fin)) FOR UPDATE');
        $stmtBlk->execute([$medicoId, $fechaInicio, $turno, $horaCita]);
        $bloqueo = $stmtBlk->fetch();
        if ($bloqueo) {
            $db->rollBack();
            jsonError('El horario seleccionado se encuentra bloqueado en la agenda del médico: ' . $bloqueo['motivo'], 409);
        }

        // Control de concurrencia y solape mismo médico/fecha/hora (RF-C05, RF-B04 -> 409)
        $stmtSolape = $db->prepare('SELECT id, codigo_cita FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ? AND estado NOT IN ("cancelada") FOR UPDATE');
        $stmtSolape->execute([$medicoId, $fechaInicio, $horaCita]);
        if ($stmtSolape->fetch()) {
            $db->rollBack();
            jsonError('El médico ya cuenta con una cita programada en esa fecha y hora (Conflicto de horario 409). Por favor elija otro horario o turno.', 409);
        }

        $stmtPac = $db->prepare('SELECT id, nombre, apellidos FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtPac->execute([$dni]);
        $pac = $stmtPac->fetch();
        $usuarioId = $user ? (int)$user['id'] : null;
        $nombrePaciente = $pac ? "{$pac['nombre']} {$pac['apellidos']}" : trim((string)($body['nombre_paciente'] ?? ($user['nombre'] ?? "Paciente DNI {$dni}")));

        if ($pac) {
            $pacienteId = (int)$pac['id'];
        } else {
            $pacUsuarioId = null;
            if ($usuarioId !== null) {
                $stmtU = $db->prepare('SELECT id FROM pacientes WHERE usuario_id = ? LIMIT 1');
                $stmtU->execute([$usuarioId]);
                if (!$stmtU->fetch()) {
                    $pacUsuarioId = $usuarioId;
                }
            }
            $partes = preg_split('/\s+/', trim($nombrePaciente), 2);
            $pacNom = $partes[0] ?: 'Paciente';
            $pacApe = $partes[1] ?? "DNI {$dni}";
            $insPac = $db->prepare('INSERT INTO pacientes (usuario_id, dni, nombre, apellidos, edad, fecha_nacimiento, genero, telefono, email, direccion, departamento, provincia, distrito, nombre_emergencia, apellido_emergencia, parentesco_emergencia, telefono_emergencia, tipo_sangre) VALUES (?, ?, ?, ?, 30, "1996-01-01", "otro", ?, ?, "Pendiente de registro", "Lima", "Lima", "Independencia", "Familiar", "Emergencia", "Familiar", ?, "O+")');
            $insPac->execute([$pacUsuarioId, $dni, $pacNom, $pacApe, $telefono, $email, $telefono]);
            $pacienteId = (int)$db->lastInsertId();
        }

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

    queueNotification(
        "{$email} / WhatsApp {$telefono}",
        'ambos',
        "Confirmación de Cita {$codigoCita} - {$esp['nombre']}",
        "Estimado(a) {$nombrePaciente}, su cita {$codigoCita} en {$esp['nombre']} con {$medicoNombre} ha sido programada para el {$fechaInicio} a las " . substr($horaCita, 0, 5) . " (Turno {$turno}). Recuerde asistir 15 minutos antes (Tolerancia máxima: 15 min).",
        'cita',
        $citaId
    );
    logAudit($user, 'AGENDAR_CITA', 'citas', $codigoCita, "Cita agendada para DNI {$dni} con médico ID {$medicoId} el {$fechaInicio} {$horaCita}", 201);

    jsonResponse([
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
            'notificacion' => 'Enviada por WhatsApp y Email (<60s)'
        ]
    ], 201);
}

if (count($segments) === 2 && $segments[0] === 'citas' && is_numeric($segments[1]) && ($method === 'PUT' || $method === 'DELETE')) {
    $user = requireAuth();
    $citaId = (int)$segments[1];

    $stmt = $db->prepare('SELECT * FROM citas WHERE id = ? LIMIT 1');
    $stmt->execute([$citaId]);
    $cita = $stmt->fetch();
    if (!$cita) {
        jsonError('La cita indicada no existe.', 404);
    }

    $citaTimestamp = strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']);
    $horasRestantes = ($citaTimestamp - time()) / 3600;

    if ($method === 'DELETE') {
        if ($user['rol'] === 'paciente' && $horasRestantes < 24) {
            jsonError('No es posible cancelar la cita con menos de 24 horas de anticipación según nuestros Términos y Condiciones (Regla 24h).', 422);
        }
        $db->prepare('UPDATE citas SET estado = "cancelada" WHERE id = ?')->execute([$citaId]);
        queueNotification($cita['email_paciente'], 'ambos', "Cancelación de Cita {$cita['codigo_cita']}", "Su cita {$cita['codigo_cita']} ha sido cancelada.", 'cita', $citaId);
        logAudit($user, 'CANCELAR_CITA', 'citas', $cita['codigo_cita'], 'Cita cancelada cumpliendo regla >24h', 200);
        jsonResponse(['ok' => true, 'message' => 'Cita cancelada correctamente.']);
    }

    $body = getJsonBody();

    // Si es paciente o solicita reprogramar fecha/hora (RF-C06)
    if (!empty($body['accion']) && $body['accion'] === 'reprogramar' || ($user['rol'] === 'paciente')) {
        if ($horasRestantes < 24 && $user['rol'] === 'paciente') {
            jsonError('Las reprogramaciones deben realizarse con al menos 24 horas de anticipación (Regla 24h TyC).', 422);
        }
        $nuevaFecha = trim((string)($body['fecha_cita'] ?? $cita['fecha_cita']));
        $nuevaHora = trim((string)($body['hora_cita'] ?? $cita['hora_cita']));
        if (strlen($nuevaHora) === 5) $nuevaHora .= ':00';
        $nuevoTurno = trim((string)($body['turno'] ?? $cita['turno']));

        $stmtSolape = $db->prepare('SELECT id FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ? AND id != ? AND estado NOT IN ("cancelada")');
        $stmtSolape->execute([$cita['medico_id'], $nuevaFecha, $nuevaHora, $citaId]);
        if ($stmtSolape->fetch()) {
            jsonError('El nuevo horario elegido ya está ocupado para este médico.', 409);
        }

        $db->prepare('UPDATE citas SET fecha_cita = ?, hora_cita = ?, turno = ?, estado = "reprogramada" WHERE id = ?')
           ->execute([$nuevaFecha, $nuevaHora, $nuevoTurno, $citaId]);
        queueNotification($cita['email_paciente'], 'ambos', "Reprogramación de Cita {$cita['codigo_cita']}", "Su cita {$cita['codigo_cita']} fue reprogramada para el {$nuevaFecha} a las " . substr($nuevaHora, 0, 5), 'cita', $citaId);
        logAudit($user, 'REPROGRAMAR_CITA', 'citas', $cita['codigo_cita'], "Cita reprogramada a {$nuevaFecha} {$nuevaHora}", 200);
        jsonResponse(['ok' => true, 'message' => 'Cita reprogramada exitosamente.']);
    }

    // Si es médico o admin cambiando el estado de atención (RF-M03: en_atencion, finalizada, no_show)
    if (!in_array($user['rol'], ['medico', 'admin'], true)) {
        jsonError('Solo médicos o administradores pueden cambiar el estado clínico de la cita.', 403);
    }

    $nuevoEstado = trim((string)($body['estado'] ?? ''));
    $observaciones = trim((string)($body['observaciones_atencion'] ?? $cita['observaciones_atencion'] ?? ''));

    if (!in_array($nuevoEstado, ['programada', 'en_atencion', 'finalizada', 'no_show', 'cancelada'], true)) {
        jsonError('Estado de cita no válido.', 422);
    }

    if ($nuevoEstado === 'finalizada') {
        $stmtHcCount = $db->prepare('SELECT COUNT(*) FROM historias_clinicas WHERE cita_id = ? OR dni_paciente = ?');
        $stmtHcCount->execute([$citaId, $cita['dni_paciente']]);
        if ((int)$stmtHcCount->fetchColumn() === 0 && $observaciones === '') {
            jsonError('Para finalizar la cita es obligatorio haber registrado la historia clínica o ingresar las observaciones médicas de cierre (RF-M03).', 422);
        }
        $db->prepare('UPDATE medicos SET consultas_realizadas = consultas_realizadas + 1 WHERE id = ?')->execute([$cita['medico_id']]);
    }

    $penalidad = 0.00;
    if ($nuevoEstado === 'no_show') {
        $penalidad = 20.00; // Penalidad por inasistencia pasados 15 min de tolerancia según TyC
        $db->prepare('UPDATE pacientes SET penalidad_noshow = penalidad_noshow + 20.00, deuda_pendiente = deuda_pendiente + 20.00 WHERE dni = ?')
           ->execute([$cita['dni_paciente']]);
        if ($observaciones === '') {
            $observaciones = 'Inasistencia (No-Show) registrada tras cumplirse los 15 minutos de tolerancia. Cargo administrativo S/ 20.00 según TyC.';
        }
    }

    $db->prepare('UPDATE citas SET estado = ?, observaciones_atencion = ?, penalidad_aplicada = ? WHERE id = ?')
       ->execute([$nuevoEstado, $observaciones, $penalidad, $citaId]);

    logAudit($user, 'CAMBIO_ESTADO_CITA', 'citas', $cita['codigo_cita'], "Estado actualizado a {$nuevoEstado}", 200);
    jsonResponse(['ok' => true, 'message' => "Estado de la cita actualizado a {$nuevoEstado}."]);
}

if (count($segments) === 3 && $segments[0] === 'citas' && is_numeric($segments[1]) && $segments[2] === 'derivar' && $method === 'POST') {
    $user = requireAuth(['medico', 'admin']);
    $citaOrigenId = (int)$segments[1];
    $body = getJsonBody();

    $stmtOrig = $db->prepare('SELECT * FROM citas WHERE id = ? LIMIT 1');
    $stmtOrig->execute([$citaOrigenId]);
    $origen = $stmtOrig->fetch();
    if (!$origen) {
        jsonError('Cita de origen no encontrada.', 404);
    }

    $espDestinoId = (int)($body['especialidad_id'] ?? 0);
    $medicoDestinoId = (int)($body['medico_id'] ?? 0);
    $fechaDerivada = trim((string)($body['fecha_cita'] ?? date('Y-m-d', strtotime('+2 days'))));
    $horaDerivada = trim((string)($body['hora_cita'] ?? '10:00:00'));
    if (strlen($horaDerivada) === 5) $horaDerivada .= ':00';
    $motivoDerivacion = trim((string)($body['motivo'] ?? 'Interconsulta médica especializada'));

    if ($espDestinoId <= 0 || $motivoDerivacion === '') {
        jsonError('Seleccione la especialidad destino e indique el motivo clínico de la derivación.', 422);
    }

    if ($medicoDestinoId <= 0) {
        $stmtM = $db->prepare('SELECT id FROM medicos WHERE especialidad_id = ? AND activo = 1 LIMIT 1');
        $stmtM->execute([$espDestinoId]);
        $medicoDestinoId = (int)($stmtM->fetchColumn() ?: 1);
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
        $citaOrigenId
    ]);
    $nuevaId = (int)$db->lastInsertId();

    queueNotification($origen['email_paciente'], 'ambos', "Interconsulta Derivada {$codigoDerivada}", "Se ha generado su cita de interconsulta {$codigoDerivada} para el {$fechaDerivada}. Motivo: {$motivoDerivacion}", 'derivacion', $nuevaId);
    logAudit($user, 'DERIVAR_PACIENTE_INTERCONSULTA', 'citas', $codigoDerivada, "Derivación desde cita #{$citaOrigenId} a especialidad #{$espDestinoId}", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Interconsulta / derivación creada exitosamente.',
        'codigo_cita' => $codigoDerivada,
        'id' => $nuevaId
    ], 201);
}

// ============================================================
// 6. RECETAS DIGITALES (RF-M05, RF-C09)
// ============================================================

if ($route === '/recetas' && $method === 'GET') {
    $user = requireAuth();
    $dni = trim((string)($_GET['dni'] ?? ''));

    $sql = 'SELECT r.*, m.nombre AS medico_nombre, m.especialidad_nombre, m.cmp, p.nombre AS paciente_nombre, p.apellidos AS paciente_apellidos
            FROM recetas r
            JOIN medicos m ON m.id = r.medico_id
            LEFT JOIN pacientes p ON p.dni = r.dni_paciente';
    $params = [];

    if ($user['rol'] === 'paciente') {
        $pacDni = $user['paciente']['dni'] ?? $dni;
        $sql .= ' WHERE r.dni_paciente = ?';
        $params[] = $pacDni;
    } elseif ($dni !== '') {
        $sql .= ' WHERE r.dni_paciente = ?';
        $params[] = $dni;
    }
    $sql .= ' ORDER BY r.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['medicamentos'] = json_decode($r['medicamentos_json'], true) ?: [];
    }
    jsonResponse(['ok' => true, 'recetas' => $rows]);
}

if ($route === '/recetas' && $method === 'POST') {
    $user = requireAuth(['medico', 'admin']);
    $body = getJsonBody();

    $dni = trim((string)($body['dni_paciente'] ?? ''));
    $diagnostico = trim((string)($body['diagnostico'] ?? ''));
    $indicaciones = trim((string)($body['indicaciones'] ?? ''));
    $medicamentos = $body['medicamentos'] ?? [];
    $citaId = !empty($body['cita_id']) ? (int)$body['cita_id'] : null;

    if (!preg_match('/^\d{8}$/', $dni) || $diagnostico === '' || empty($medicamentos)) {
        jsonError('DNI del paciente, diagnóstico y al menos un medicamento son obligatorios.', 422);
    }

    $stmtP = $db->prepare('SELECT id, email FROM pacientes WHERE dni = ? LIMIT 1');
    $stmtP->execute([$dni]);
    $pac = $stmtP->fetch();
    if (!$pac) {
        jsonError('Paciente con DNI ' . $dni . ' no encontrado.', 404);
    }

    $medicoId = (int)($user['medico']['id'] ?? $body['medico_id'] ?? 1);
    $codigoReceta = 'REC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $emision = date('Y-m-d');
    $vencimiento = date('Y-m-d', strtotime('+15 days'));

    $ins = $db->prepare('INSERT INTO recetas (codigo_receta, cita_id, paciente_id, dni_paciente, medico_id, diagnostico, medicamentos_json, indicaciones, estado, fecha_emision, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "vigente", ?, ?)');
    $ins->execute([$codigoReceta, $citaId, $pac['id'], $dni, $medicoId, $diagnostico, json_encode($medicamentos, JSON_UNESCAPED_UNICODE), $indicaciones, $emision, $vencimiento]);
    $recetaId = (int)$db->lastInsertId();

    if (!empty($pac['email'])) {
        queueNotification($pac['email'], 'email', "Nueva Receta Digital {$codigoReceta}", "Su médico ha emitido la receta digital {$codigoReceta}. Puede utilizar este código en nuestra Farmacia Virtual.", 'receta', $recetaId);
    }
    logAudit($user, 'EMITIR_RECETA_DIGITAL', 'recetas', $codigoReceta, "Receta emitida para DNI {$dni}", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Receta digital emitida exitosamente.',
        'codigo_receta' => $codigoReceta,
        'id' => $recetaId
    ], 201);
}

// ============================================================
// 7. FARMACIA, STOCK ATÓMICO, VALIDACIÓN DE RECETA Y PAGOS/EPS (RF-C09, RF-B04, RF-B05, RF-B07)
// ============================================================

if ($route === '/productos' && $method === 'GET') {
    $search = trim((string)($_GET['search'] ?? ''));
    $user = getCurrentUser();
    $showAll = ($user && $user['rol'] === 'admin' && !empty($_GET['all']));
    $whereActive = $showAll ? '1=1' : 'activo = 1';
    if ($search !== '') {
        $stmt = $db->prepare("SELECT * FROM productos_farmacia WHERE {$whereActive} AND (nombre LIKE ? OR categoria LIKE ?) ORDER BY id ASC");
        $like = "%{$search}%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $db->query("SELECT * FROM productos_farmacia WHERE {$whereActive} ORDER BY id ASC");
    }
    jsonResponse(['ok' => true, 'productos' => $stmt->fetchAll()]);
}

if ($route === '/productos' && $method === 'POST') {
    $user = requireAuth(['admin']);
    $body = getJsonBody();
    $nombre = trim((string)($body['nombre'] ?? ''));
    $categoria = trim((string)($body['categoria'] ?? 'General'));
    $precio = (float)($body['precio'] ?? 0);
    $stock = (int)($body['stock'] ?? 0);
    $reqReceta = !empty($body['requiere_receta']) ? 1 : 0;
    $imagen = trim((string)($body['imagen'] ?? 'img/farmacia.png'));

    if ($nombre === '' || $precio <= 0) {
        jsonError('Nombre y precio mayor a 0 son obligatorios.', 422);
    }
    $ins = $db->prepare('INSERT INTO productos_farmacia (nombre, categoria, precio, stock, requiere_receta, imagen) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([$nombre, $categoria, $precio, $stock, $reqReceta, $imagen]);
    jsonResponse(['ok' => true, 'message' => 'Producto creado.', 'id' => (int)$db->lastInsertId()], 201);
}

if (count($segments) === 2 && $segments[0] === 'productos' && is_numeric($segments[1]) && $method === 'PUT') {
    $user = requireAuth(['admin']);
    $id = (int)$segments[1];
    $body = getJsonBody();
    $upd = $db->prepare('UPDATE productos_farmacia SET nombre = COALESCE(?, nombre), categoria = COALESCE(?, categoria), precio = COALESCE(?, precio), stock = COALESCE(?, stock), requiere_receta = COALESCE(?, requiere_receta), activo = COALESCE(?, activo) WHERE id = ?');
    $upd->execute([
        $body['nombre'] ?? null,
        $body['categoria'] ?? null,
        isset($body['precio']) ? (float)$body['precio'] : null,
        isset($body['stock']) ? (int)$body['stock'] : null,
        isset($body['requiere_receta']) ? (int)$body['requiere_receta'] : null,
        isset($body['activo']) ? (int)$body['activo'] : null,
        $id
    ]);
    logAudit($user, 'ACTUALIZAR_STOCK_PRODUCTO', 'productos_farmacia', (string)$id, 'Actualización de producto/stock en farmacia', 200);
    jsonResponse(['ok' => true, 'message' => 'Producto y stock actualizados.']);
}

if ($route === '/pedidos' && $method === 'GET') {
    $user = requireAuth();
    if ($user['rol'] === 'paciente') {
        $dni = $user['paciente']['dni'] ?? '';
        $stmt = $db->prepare('SELECT * FROM pedidos_farmacia WHERE usuario_id = ? OR dni_cliente = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id'], $dni]);
    } else {
        $stmt = $db->query('SELECT * FROM pedidos_farmacia ORDER BY created_at DESC LIMIT 100');
    }
    $pedidos = $stmt->fetchAll();
    foreach ($pedidos as &$ped) {
        $stIt = $db->prepare('SELECT * FROM pedido_items WHERE pedido_id = ?');
        $stIt->execute([$ped['id']]);
        $ped['items'] = $stIt->fetchAll();
    }
    jsonResponse(['ok' => true, 'pedidos' => $pedidos]);
}

if ($route === '/pedidos' && $method === 'POST') {
    $user = getCurrentUser();
    $body = getJsonBody();

    $items = $body['items'] ?? [];
    $dni = trim((string)($body['dni'] ?? $user['paciente']['dni'] ?? ''));
    $nombreCliente = trim((string)($body['nombre'] ?? $user['nombre'] ?? 'Cliente Farmacia'));
    $metodoPago = trim((string)($body['metodo_pago'] ?? 'tarjeta_visa'));
    $codigoReceta = strtoupper(trim((string)($body['codigo_receta'] ?? '')));
    $usarEps = !empty($body['usar_eps']);

    if (empty($items) || !is_array($items)) {
        jsonError('El carrito de compras está vacío.', 422);
    }
    if (!preg_match('/^\d{8}$/', $dni)) {
        jsonError('Ingrese un DNI válido de 8 dígitos para la emisión de la boleta electrónica.', 422, ['dni' => 'DNI de 8 dígitos requerido']);
    }

    $db->beginTransaction();
    try {
        $subtotal = 0.0;
        $exigeReceta = false;
        $nombresConReceta = [];
        $itemsProcesados = [];

        foreach ($items as $item) {
            $prodId = (int)($item['id'] ?? $item['producto_id'] ?? 0);
            $cant = (int)($item['quantity'] ?? $item['cantidad'] ?? 1);
            if ($prodId <= 0 || $cant <= 0) {
                continue;
            }

            $stmtP = $db->prepare('SELECT * FROM productos_farmacia WHERE id = ? AND activo = 1 FOR UPDATE');
            $stmtP->execute([$prodId]);
            $prod = $stmtP->fetch();
            if (!$prod) {
                $db->rollBack();
                jsonError("El producto ID {$prodId} no existe.", 404);
            }
            if ((int)$prod['stock'] < $cant) {
                $db->rollBack();
                jsonError("Stock insuficiente para {$prod['nombre']} (Disponible: {$prod['stock']}, Solicitado: {$cant}).", 409);
            }
            if ((int)$prod['requiere_receta'] === 1) {
                $exigeReceta = true;
                $nombresConReceta[] = $prod['nombre'];
            }

            $lineaSub = (float)$prod['precio'] * $cant;
            $subtotal += $lineaSub;
            $itemsProcesados[] = [
                'producto_id' => (int)$prod['id'],
                'nombre' => $prod['nombre'],
                'cantidad' => $cant,
                'precio' => (float)$prod['precio'],
                'subtotal' => $lineaSub
            ];
        }

        if ($exigeReceta) {
            if ($codigoReceta === '') {
                $db->rollBack();
                jsonError('Los siguientes medicamentos exigen receta médica vigente (' . implode(', ', $nombresConReceta) . '). Ingrese su código de receta digital (ej. REC-2026-001).', 422, [
                    'codigo_receta' => 'Receta médica obligatoria para antibióticos/medicamentos controlados.'
                ]);
            }
            $stmtRec = $db->prepare('SELECT * FROM recetas WHERE codigo_receta = ? AND estado = "vigente" FOR UPDATE');
            $stmtRec->execute([$codigoReceta]);
            $recetaRow = $stmtRec->fetch();
            if (!$recetaRow) {
                $db->rollBack();
                jsonError("El código de receta '{$codigoReceta}' no existe o ya fue utilizado/vencido.", 422, [
                    'codigo_receta' => 'Código de receta inválido o vencido.'
                ]);
            }
        }

        foreach ($itemsProcesados as $ip) {
            $db->prepare('UPDATE productos_farmacia SET stock = stock - ? WHERE id = ?')
               ->execute([$ip['cantidad'], $ip['producto_id']]);
        }

        $descuentoEps = 0.0;
        $montoNoCubierto = $subtotal;
        $stmtPac = $db->prepare('SELECT id, tiene_seguro, aseguradora, cobertura_eps_pct FROM pacientes WHERE dni = ? LIMIT 1');
        $stmtPac->execute([$dni]);
        $pac = $stmtPac->fetch();

        if ($usarEps && $pac && (int)$pac['tiene_seguro'] === 1 && (float)$pac['cobertura_eps_pct'] > 0) {
            $pct = (float)$pac['cobertura_eps_pct'] / 100.0;
            $descuentoEps = round($subtotal * $pct, 2);
            $montoNoCubierto = round($subtotal - $descuentoEps, 2);
        }
        $totalPagado = $montoNoCubierto;

        $codigoBoleta = 'BOL-F' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $insPed = $db->prepare('INSERT INTO pedidos_farmacia (codigo_boleta, usuario_id, dni_cliente, nombre_cliente, subtotal, descuento_eps, total_pagado, monto_no_cubierto, metodo_pago, codigo_receta, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pagado")');
        $insPed->execute([
            $codigoBoleta,
            $user['id'] ?? null,
            $dni,
            $nombreCliente,
            $subtotal,
            $descuentoEps,
            $totalPagado,
            $montoNoCubierto,
            $metodoPago,
            $codigoReceta ?: null
        ]);
        $pedidoId = (int)$db->lastInsertId();

        $insItem = $db->prepare('INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($itemsProcesados as $ip) {
            $insItem->execute([$pedidoId, $ip['producto_id'], $ip['nombre'], $ip['cantidad'], $ip['precio'], $ip['subtotal']]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    logAudit($user, 'COMPRA_FARMACIA', 'pedidos_farmacia', $codigoBoleta, "Pedido pagado S/ {$totalPagado} (DNI {$dni})", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Pago aprobado y boleta electrónica generada.',
        'boleta' => [
            'pedido_id' => $pedidoId,
            'codigo_boleta' => $codigoBoleta,
            'dni_cliente' => $dni,
            'nombre_cliente' => $nombreCliente,
            'subtotal' => $subtotal,
            'descuento_eps' => $descuentoEps,
            'total_pagado' => $totalPagado,
            'metodo_pago' => $metodoPago,
            'codigo_receta' => $codigoReceta ?: null,
            'fecha' => date('Y-m-d H:i:s'),
            'items' => $itemsProcesados
        ]
    ], 201);
}

// ============================================================
// 8. LABORATORIO, ÓRDENES Y RESULTADOS CON CÓDIGO (RF-C10, RF-M05, RF-M06)
// ============================================================

if ($route === '/laboratorio/catalogo' && $method === 'GET') {
    $stmt = $db->query('SELECT * FROM catalogo_laboratorio WHERE activo = 1 ORDER BY id ASC');
    jsonResponse(['ok' => true, 'analisis' => $stmt->fetchAll()]);
}

if ($route === '/ordenes-lab' && $method === 'GET') {
    $user = requireAuth();
    if ($user['rol'] === 'paciente') {
        $dni = $user['paciente']['dni'] ?? '';
        $stmt = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE dni_paciente = ? ORDER BY created_at DESC');
        $stmt->execute([$dni]);
    } elseif ($user['rol'] === 'medico') {
        $medicoId = (int)($user['medico']['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM ordenes_laboratorio WHERE medico_id = ? OR dni_paciente IN (SELECT dni_paciente FROM citas WHERE medico_id = ?) ORDER BY created_at DESC');
        $stmt->execute([$medicoId, $medicoId]);
    } else {
        $stmt = $db->query('SELECT * FROM ordenes_laboratorio ORDER BY created_at DESC');
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['analisis'] = json_decode($r['analisis_json'], true) ?: [];
        $r['valores_resultado'] = $r['valores_resultado_json'] ? json_decode($r['valores_resultado_json'], true) : [];
    }
    jsonResponse(['ok' => true, 'ordenes' => $rows]);
}

if ($route === '/ordenes-lab' && $method === 'POST') {
    $user = getCurrentUser();
    $body = getJsonBody();

    $dni = trim((string)($body['dni_paciente'] ?? $body['dni'] ?? $user['paciente']['dni'] ?? ''));
    $nombrePaciente = trim((string)($body['nombre_paciente'] ?? $body['nombre'] ?? $user['nombre'] ?? ''));
    $analisisItems = $body['analisis'] ?? [];
    $metodoPago = trim((string)($body['metodo_pago'] ?? 'tarjeta_visa'));
    $citaId = !empty($body['cita_id']) ? (int)$body['cita_id'] : null;

    if (!preg_match('/^\d{8}$/', $dni) || empty($analisisItems)) {
        jsonError('DNI válido de 8 dígitos y al menos un análisis son obligatorios.', 422);
    }

    $stmtP = $db->prepare('SELECT id, nombre, apellidos, email FROM pacientes WHERE dni = ? LIMIT 1');
    $stmtP->execute([$dni]);
    $pac = $stmtP->fetch();
    $pacienteId = $pac ? (int)$pac['id'] : null;
    if ($nombrePaciente === '' && $pac) {
        $nombrePaciente = "{$pac['nombre']} {$pac['apellidos']}";
    } elseif ($nombrePaciente === '') {
        $nombrePaciente = "Paciente DNI {$dni}";
    }

    $total = 0.0;
    $detalle = [];
    foreach ($analisisItems as $a) {
        $nombreA = trim((string)($a['nombre'] ?? 'Análisis Clínico'));
        $cant = (int)($a['cantidad'] ?? 1);
        $stmtCat = $db->prepare('SELECT precio FROM catalogo_laboratorio WHERE nombre = ? LIMIT 1');
        $stmtCat->execute([$nombreA]);
        $precio = (float)($stmtCat->fetchColumn() ?: ($a['precio'] ?? 40.00));
        $total += $precio * $cant;
        $detalle[] = ['nombre' => $nombreA, 'precio' => $precio, 'cantidad' => $cant];
    }

    $medicoId = ($user && $user['rol'] === 'medico') ? (int)($user['medico']['id'] ?? 1) : (!empty($body['medico_id']) ? (int)$body['medico_id'] : null);
    $codigoOrden = 'LAB-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $codigoBoleta = 'BOL-L' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    $ins = $db->prepare('INSERT INTO ordenes_laboratorio (codigo_orden, cita_id, paciente_id, dni_paciente, nombre_paciente, medico_id, analisis_json, total, metodo_pago, codigo_boleta, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente_muestra")');
    $ins->execute([$codigoOrden, $citaId, $pacienteId, $dni, $nombrePaciente, $medicoId, json_encode($detalle, JSON_UNESCAPED_UNICODE), $total, $metodoPago, $codigoBoleta]);
    $ordenId = (int)$db->lastInsertId();

    logAudit($user, 'CREAR_ORDEN_LABORATORIO', 'ordenes_laboratorio', $codigoOrden, "Orden de laboratorio generada para DNI {$dni} (Total S/ {$total})", 201);

    jsonResponse([
        'ok' => true,
        'message' => 'Orden de laboratorio registrada con éxito.',
        'orden' => [
            'id' => $ordenId,
            'codigo_orden' => $codigoOrden,
            'codigo_boleta' => $codigoBoleta,
            'dni_paciente' => $dni,
            'nombre_paciente' => $nombrePaciente,
            'total' => $total,
            'analisis' => $detalle,
            'estado' => 'pendiente_muestra'
        ]
    ], 201);
}

if (count($segments) === 2 && $segments[0] === 'ordenes-lab' && is_numeric($segments[1]) && $method === 'PUT') {
    $user = requireAuth(['medico', 'admin']);
    $ordenId = (int)$segments[1];
    $body = getJsonBody();

    $estado = in_array($body['estado'] ?? '', ['pendiente_muestra', 'en_proceso', 'publicado'], true) ? $body['estado'] : 'publicado';
    $resumen = trim((string)($body['resultado_resumen'] ?? 'Valores bioquímicos dentro de límites normales.'));
    $observaciones = trim((string)($body['observaciones_lab'] ?? 'Validado por Laboratorio Clínico Centenario.'));
    $valores = $body['valores_resultado'] ?? [
        ['parametro' => 'Resultado General', 'valor' => $resumen, 'referencia' => 'Valores estándar', 'estado' => 'Normal']
    ];

    $db->prepare('UPDATE ordenes_laboratorio SET estado = ?, resultado_resumen = ?, valores_resultado_json = ?, observaciones_lab = ?, fecha_publicacion = NOW() WHERE id = ?')
       ->execute([$estado, $resumen, json_encode($valores, JSON_UNESCAPED_UNICODE), $observaciones, $ordenId]);

    $stmtO = $db->prepare('SELECT codigo_orden, dni_paciente FROM ordenes_laboratorio WHERE id = ?');
    $stmtO->execute([$ordenId]);
    $ord = $stmtO->fetch();

    logAudit($user, 'PUBLICAR_RESULTADO_LAB', 'ordenes_laboratorio', $ord['codigo_orden'] ?? (string)$ordenId, 'Publicación de resultados clínicos de laboratorio', 200);
    jsonResponse(['ok' => true, 'message' => 'Resultados de laboratorio publicados correctamente.']);
}

if (count($segments) === 2 && $segments[0] === 'resultados' && $method === 'GET') {
    $codigo = strtoupper(trim($segments[1]));
    $dniQuery = trim((string)($_GET['dni'] ?? ''));
    $user = getCurrentUser();

    $stmt = $db->prepare('SELECT o.*, m.nombre AS medico_nombre FROM ordenes_laboratorio o LEFT JOIN medicos m ON m.id = o.medico_id WHERE o.codigo_orden = ? LIMIT 1');
    $stmt->execute([$codigo]);
    $orden = $stmt->fetch();
    if (!$orden) {
        jsonError("No se encontró ninguna orden de laboratorio con el código {$codigo}.", 404);
    }

    // Regla RF-C10 y RF-M06: Solo el paciente dueño (por sesión o verificando su DNI), el médico tratante o admin
    $autorizado = false;
    if ($user) {
        if ($user['rol'] === 'admin') {
            $autorizado = true;
        } elseif ($user['rol'] === 'medico') {
            $medId = (int)($user['medico']['id'] ?? 0);
            if ((int)$orden['medico_id'] === $medId) {
                $autorizado = true;
            } else {
                $stC = $db->prepare('SELECT COUNT(*) FROM citas WHERE dni_paciente = ? AND medico_id = ?');
                $stC->execute([$orden['dni_paciente'], $medId]);
                if ((int)$stC->fetchColumn() > 0) $autorizado = true;
            }
        } elseif ($user['rol'] === 'paciente') {
            $userDni = $user['paciente']['dni'] ?? '';
            if ($userDni === $orden['dni_paciente'] || (int)$orden['paciente_id'] === (int)($user['paciente']['id'] ?? -1)) {
                $autorizado = true;
            }
        }
    }
    if (!$autorizado && $dniQuery !== '' && $dniQuery === $orden['dni_paciente']) {
        $autorizado = true;
    }

    if (!$autorizado) {
        logAudit($user, 'ACCESO_DENEGADO_RESULTADO_LAB', 'ordenes_laboratorio', $codigo, 'Intento de acceso sin ser dueño ni médico tratante', 403);
        jsonError('Acceso restringido (RF-C10 / Ley 29733): Solo el titular del análisis (verificando su sesión o DNI) o su médico tratante pueden acceder a estos resultados.', 403);
    }

    $orden['analisis'] = json_decode($orden['analisis_json'], true) ?: [];
    $orden['valores_resultado'] = $orden['valores_resultado_json'] ? json_decode($orden['valores_resultado_json'], true) : [];

    logAudit($user, 'CONSULTA_RESULTADO_LAB', 'ordenes_laboratorio', $codigo, "Descarga/lectura de resultado de laboratorio {$codigo}", 200);
    jsonResponse(['ok' => true, 'resultado' => $orden]);
}

// ============================================================
// 9. CAMPAÑAS DE SALUD Y CUPOS ATÓMICOS (RF-C11, RF-B04, RF-B07)
// ============================================================

if (($route === '/campanas' || $route === '/campañas') && $method === 'GET') {
    $stmt = $db->query('SELECT * FROM campanas ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['cupos_disponibles'] = max(0, (int)$r['cupos_totales'] - (int)$r['cupos_ocupados']);
    }
    jsonResponse(['ok' => true, 'campanas' => $rows]);
}

if (($route === '/campanas' || $route === '/campañas') && $method === 'POST') {
    $user = requireAuth(['admin']);
    $body = getJsonBody();
    $titulo = trim((string)($body['titulo'] ?? ''));
    $descripcion = trim((string)($body['descripcion'] ?? ''));
    $icono = trim((string)($body['icono'] ?? 'fas fa-heartbeat'));
    $fechaInicio = !empty($body['fecha_inicio']) ? trim((string)$body['fecha_inicio']) : date('Y-m-d H:i:s');
    $fechaFin = !empty($body['fecha_fin']) ? trim((string)$body['fecha_fin']) : date('Y-m-d H:i:s', strtotime('+5 days'));
    $rango = trim((string)($body['rango_fechas_texto'] ?? ''));
    if ($rango === '') {
        $rango = date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
    }
    $cuposTotales = (int)($body['cupos_totales'] ?? $body['cupo_maximo'] ?? 500);
    $estado = in_array($body['estado'] ?? '', ['activa', 'pronto', 'finalizada'], true) ? $body['estado'] : 'activa';

    if ($titulo === '' || $cuposTotales <= 0) {
        jsonError('Título y cupos totales (>0) son obligatorios.', 422);
    }
    $ins = $db->prepare('INSERT INTO campanas (titulo, descripcion, icono, rango_fechas_texto, fecha_inicio, fecha_fin, cupos_totales, cupos_ocupados, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
    $ins->execute([$titulo, $descripcion, $icono, $rango, $fechaInicio, $fechaFin, $cuposTotales, $estado]);
    $id = (int)$db->lastInsertId();
    logAudit($user, 'CREAR_CAMPANA', 'campanas', (string)$id, "Campaña creada: {$titulo}", 201);
    jsonResponse(['ok' => true, 'message' => 'Campaña creada exitosamente.', 'id' => $id], 201);
}

if (count($segments) === 2 && ($segments[0] === 'campanas' || $segments[0] === 'campañas') && is_numeric($segments[1]) && $method === 'PUT') {
    $user = requireAuth(['admin']);
    $id = (int)$segments[1];
    $body = getJsonBody();
    $cuposTot = isset($body['cupos_totales']) ? (int)$body['cupos_totales'] : (isset($body['cupo_maximo']) ? (int)$body['cupo_maximo'] : null);
    $upd = $db->prepare('UPDATE campanas SET titulo = COALESCE(?, titulo), descripcion = COALESCE(?, descripcion), rango_fechas_texto = COALESCE(?, rango_fechas_texto), cupos_totales = COALESCE(?, cupos_totales), cupos_ocupados = COALESCE(?, cupos_ocupados), estado = COALESCE(?, estado), fecha_inicio = COALESCE(?, fecha_inicio), fecha_fin = COALESCE(?, fecha_fin) WHERE id = ?');
    $upd->execute([
        $body['titulo'] ?? null,
        $body['descripcion'] ?? null,
        $body['rango_fechas_texto'] ?? null,
        $cuposTot,
        isset($body['cupos_ocupados']) ? (int)$body['cupos_ocupados'] : null,
        $body['estado'] ?? null,
        $body['fecha_inicio'] ?? null,
        $body['fecha_fin'] ?? null,
        $id
    ]);
    logAudit($user, 'ACTUALIZAR_CAMPANA', 'campanas', (string)$id, 'Actualización de cupos/estado de campaña', 200);
    jsonResponse(['ok' => true, 'message' => 'Campaña actualizada correctamente.']);
}

if (count($segments) === 3 && ($segments[0] === 'campanas' || $segments[0] === 'campañas') && is_numeric($segments[1]) && $segments[2] === 'inscribir' && $method === 'POST') {
    $campanaId = (int)$segments[1];
    $user = getCurrentUser();
    $body = getJsonBody();

    $dni = trim((string)($body['dni'] ?? ''));
    $nombre = trim((string)($body['nombre'] ?? $body['name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));

    if (!preg_match('/^\d{8}$/', $dni) || strlen($nombre) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('DNI válido de 8 dígitos, nombre completo y correo electrónico son obligatorios.', 422);
    }

    $db->beginTransaction();
    try {
        $stmtC = $db->prepare('SELECT * FROM campanas WHERE id = ? FOR UPDATE');
        $stmtC->execute([$campanaId]);
        $campana = $stmtC->fetch();
        if (!$campana) {
            $db->rollBack();
            jsonError('La campaña seleccionada no existe.', 404);
        }
        if ($campana['estado'] !== 'activa') {
            $db->rollBack();
            jsonError('Esta campaña no se encuentra activa para inscripciones.', 409);
        }
        if ((int)$campana['cupos_ocupados'] >= (int)$campana['cupos_totales']) {
            $db->rollBack();
            jsonError('Lo sentimos, los cupos para esta campaña se han agotado (409 Cupo lleno).', 409);
        }

        $stmtDup = $db->prepare('SELECT id FROM campana_inscripciones WHERE campana_id = ? AND dni = ? LIMIT 1');
        $stmtDup->execute([$campanaId, $dni]);
        if ($stmtDup->fetch()) {
            $db->rollBack();
            jsonError("El DNI {$dni} ya se encuentra inscrito en la campaña '{$campana['titulo']}'.", 409);
        }

        $db->prepare('INSERT INTO campana_inscripciones (campana_id, dni, nombre, email, usuario_id) VALUES (?, ?, ?, ?, ?)')
           ->execute([$campanaId, $dni, $nombre, $email, $user['id'] ?? null]);

        $db->prepare('UPDATE campanas SET cupos_ocupados = cupos_ocupados + 1 WHERE id = ?')
           ->execute([$campanaId]);

        $nuevoOcupado = (int)$campana['cupos_ocupados'] + 1;
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    queueNotification($email, 'email', "Inscripción Confirmada: {$campana['titulo']}", "Hola {$nombre}, tu cupo para la campaña '{$campana['titulo']}' ha sido reservado con éxito.", 'campana', $campanaId);
    logAudit($user, 'INSCRIPCION_CAMPANA', 'campanas', (string)$campanaId, "DNI {$dni} inscrito en campaña {$campana['titulo']}", 201);

    jsonResponse([
        'ok' => true,
        'message' => "¡Inscripción exitosa para {$nombre} en {$campana['titulo']}!",
        'cupos_ocupados' => $nuevoOcupado,
        'cupos_totales' => (int)$campana['cupos_totales'],
        'cupos_disponibles' => (int)$campana['cupos_totales'] - $nuevoOcupado
    ], 201);
}

// ============================================================
// 10. PREGUNTAS FRECUENTES FAQ Y CONTACTOS/AFILIACIÓN (RF-C12, RF-C13)
// ============================================================

if ($route === '/preguntas' && $method === 'GET') {
    $stmt = $db->query('SELECT * FROM preguntas_faq ORDER BY created_at DESC');
    jsonResponse(['ok' => true, 'preguntas' => $stmt->fetchAll()]);
}

if ($route === '/preguntas' && $method === 'POST') {
    $body = getJsonBody();
    $nombre = trim((string)($body['nombre'] ?? $body['name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $pregunta = trim((string)($body['pregunta'] ?? $body['question'] ?? ''));

    $errors = [];
    if (strlen($nombre) < 3) $errors['name'] = 'Ingrese un nombre válido (mínimo 3 letras).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Correo electrónico inválido.';
    if (strlen($pregunta) < 10) $errors['question'] = 'La pregunta debe tener al menos 10 caracteres.';

    if (!empty($errors)) {
        jsonError('Error de validación al enviar la pregunta.', 422, $errors);
    }

    $ins = $db->prepare('INSERT INTO preguntas_faq (nombre, email, pregunta, estado) VALUES (?, ?, ?, "pendiente")');
    $ins->execute([$nombre, $email, $pregunta]);
    $id = (int)$db->lastInsertId();

    jsonResponse([
        'ok' => true,
        'message' => 'Tu pregunta fue enviada correctamente y será respondida por nuestro equipo.',
        'id' => $id
    ], 201);
}

if (count($segments) === 2 && $segments[0] === 'preguntas' && is_numeric($segments[1]) && $method === 'PUT') {
    $user = requireAuth(['admin']);
    $id = (int)$segments[1];
    $body = getJsonBody();
    $respuesta = trim((string)($body['respuesta'] ?? ''));
    if ($respuesta === '') {
        jsonError('La respuesta no puede estar vacía.', 422);
    }
    $db->prepare('UPDATE preguntas_faq SET respuesta = ?, estado = "respondida", respondido_por = ?, respondido_at = NOW() WHERE id = ?')
       ->execute([$respuesta, $user['id'], $id]);
    logAudit($user, 'RESPONDER_PREGUNTA_FAQ', 'preguntas_faq', (string)$id, 'Pregunta FAQ respondida por admin', 200);
    jsonResponse(['ok' => true, 'message' => 'Respuesta publicada y enviada al usuario.']);
}

if ($route === '/contactos' && $method === 'GET') {
    $user = requireAuth(['admin']);
    $stmt = $db->query('SELECT * FROM contactos_afiliacion ORDER BY created_at DESC');
    jsonResponse(['ok' => true, 'contactos' => $stmt->fetchAll()]);
}

if ($route === '/contactos' && $method === 'POST') {
    $body = getJsonBody();
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
        jsonError('Datos de afiliación inválidos.', 422, $errors);
    }

    $ins = $db->prepare('INSERT INTO contactos_afiliacion (dni, celular, correo, acepto_privacidad, estado) VALUES (?, ?, ?, 1, "ENVIADO")');
    $ins->execute([$dni, $celular, $correo ?: 'sin-correo@centenario.pe']);
    $id = (int)$db->lastInsertId();

    jsonResponse([
        'ok' => true,
        'message' => 'Solicitud de afiliación recibida en estado ENVIADO.',
        'estado' => 'ENVIADO',
        'id' => $id
    ], 201);
}

if (count($segments) === 2 && $segments[0] === 'contactos' && is_numeric($segments[1]) && $method === 'PUT') {
    $user = requireAuth(['admin']);
    $id = (int)$segments[1];
    $body = getJsonBody();
    $estado = in_array($body['estado'] ?? '', ['ENVIADO', 'EN_SEGUIMIENTO', 'AFILIADO', 'DESCARTADO'], true) ? $body['estado'] : 'EN_SEGUIMIENTO';
    $notas = trim((string)($body['notas_admin'] ?? ''));
    $db->prepare('UPDATE contactos_afiliacion SET estado = ?, notas_admin = ? WHERE id = ?')->execute([$estado, $notas, $id]);
    jsonResponse(['ok' => true, 'message' => 'Estado de afiliación actualizado.']);
}

// ============================================================
// 11. REPORTES ADMINISTRATIVOS Y AUDITORÍA (RF-B06, RF-B07)
// ============================================================

if (str_starts_with($route, '/reportes') && $method === 'GET') {
    $user = requireAuth(['admin']);

    $citasResumen = $db->query('SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN estado = "programada" OR estado = "reprogramada" THEN 1 ELSE 0 END) AS programadas,
        SUM(CASE WHEN estado = "en_atencion" THEN 1 ELSE 0 END) AS en_atencion,
        SUM(CASE WHEN estado = "finalizada" THEN 1 ELSE 0 END) AS finalizadas,
        SUM(CASE WHEN estado = "cancelada" THEN 1 ELSE 0 END) AS canceladas,
        SUM(CASE WHEN estado = "no_show" THEN 1 ELSE 0 END) AS no_shows
        FROM citas')->fetch();

    $citasPorEsp = $db->query('SELECT e.nombre AS especialidad, COUNT(c.id) AS total_citas
                               FROM especialidades e
                               LEFT JOIN citas c ON c.especialidad_id = e.id
                               GROUP BY e.id, e.nombre
                               ORDER BY total_citas DESC')->fetchAll();

    $ventasFarmacia = $db->query('SELECT COUNT(*) AS pedidos, COALESCE(SUM(total_pagado), 0) AS ingresos_pagados, COALESCE(SUM(descuento_eps), 0) AS cubierto_eps FROM pedidos_farmacia WHERE estado != "anulado"')->fetch();
    $ventasLab = $db->query('SELECT COUNT(*) AS ordenes, COALESCE(SUM(total), 0) AS ingresos_lab FROM ordenes_laboratorio')->fetch();

    $campanasStats = $db->query('SELECT id, titulo, cupos_totales, cupos_ocupados, estado FROM campanas ORDER BY id ASC')->fetchAll();
    $auditoria = $db->query('SELECT * FROM auditoria_logs ORDER BY id DESC LIMIT 60')->fetchAll();
    $notificaciones = $db->query('SELECT * FROM notificaciones ORDER BY id DESC LIMIT 40')->fetchAll();
    $usuarios = $db->query('SELECT id, email, nombre, rol, google_id, activo, created_at FROM usuarios ORDER BY id ASC')->fetchAll();

    jsonResponse([
        'ok' => true,
        'citas_resumen' => $citasResumen,
        'citas_por_especialidad' => $citasPorEsp,
        'ventas_farmacia' => $ventasFarmacia,
        'ventas_laboratorio' => $ventasLab,
        'ingresos_totales' => (float)($ventasFarmacia['ingresos_pagados'] ?? 0) + (float)($ventasLab['ingresos_lab'] ?? 0),
        'campanas' => $campanasStats,
        'auditoria' => $auditoria,
        'notificaciones' => $notificaciones,
        'usuarios' => $usuarios
    ]);
}

jsonError("Endpoint no encontrado: {$method} {$route}", 404);
