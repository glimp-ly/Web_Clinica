CREATE DATABASE IF NOT EXISTS test_clinica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE test_clinica;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS auditoria_logs;
DROP TABLE IF EXISTS notificaciones;
DROP TABLE IF EXISTS contactos_afiliacion;
DROP TABLE IF EXISTS preguntas_faq;
DROP TABLE IF EXISTS campana_inscripciones;
DROP TABLE IF EXISTS campanas;
DROP TABLE IF EXISTS ordenes_laboratorio;
DROP TABLE IF EXISTS catalogo_laboratorio;
DROP TABLE IF EXISTS pedido_items;
DROP TABLE IF EXISTS pedidos_farmacia;
DROP TABLE IF EXISTS productos_farmacia;
DROP TABLE IF EXISTS recetas;
DROP TABLE IF EXISTS historias_clinicas;
DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS medico_disponibilidad;
DROP TABLE IF EXISTS medicos;
DROP TABLE IF EXISTS especialidades;
DROP TABLE IF EXISTS pacientes;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    rol ENUM('paciente', 'medico', 'admin') NOT NULL DEFAULT 'paciente',
    google_id VARCHAR(120) NULL UNIQUE,
    avatar_url VARCHAR(255) NULL,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    reset_token VARCHAR(128) NULL,
    reset_expira DATETIME NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL UNIQUE,
    dni CHAR(8) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    edad INT NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    genero ENUM('m', 'f', 'otro') NOT NULL DEFAULT 'm',
    pais VARCHAR(60) NOT NULL DEFAULT 'Peru',
    telefono CHAR(9) NOT NULL,
    telefono_alt CHAR(9) NULL,
    email VARCHAR(150) NULL,
    direccion VARCHAR(255) NOT NULL,
    departamento VARCHAR(60) NOT NULL,
    provincia VARCHAR(80) NOT NULL,
    distrito VARCHAR(80) NOT NULL,
    nombre_emergencia VARCHAR(100) NOT NULL,
    apellido_emergencia VARCHAR(100) NOT NULL,
    parentesco_emergencia VARCHAR(40) NOT NULL,
    telefono_emergencia CHAR(9) NOT NULL,
    telefono_alt_emergencia CHAR(9) NULL,
    tipo_sangre ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    alergias TEXT NULL,
    enfermedades TEXT NULL,
    cirugias TEXT NULL,
    tiene_seguro TINYINT(1) NOT NULL DEFAULT 0,
    aseguradora VARCHAR(100) NULL,
    numero_poliza VARCHAR(50) NULL,
    cobertura_eps_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    deuda_pendiente DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    penalidad_noshow DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    acepto_terminos TINYINT(1) NOT NULL DEFAULT 1,
    consentimiento_datos TINYINT(1) NOT NULL DEFAULT 1,
    consentimiento_fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    anonimizado TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_paciente_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE especialidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(40) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(60) NOT NULL DEFAULT 'fa-stethoscope',
    descripcion TEXT NOT NULL,
    servicios_json TEXT NOT NULL,
    precio_consulta DECIMAL(10,2) NOT NULL DEFAULT 80.00,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE medicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL UNIQUE,
    cmp VARCHAR(20) NOT NULL UNIQUE,
    dni CHAR(8) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    especialidad_id INT NOT NULL,
    especialidad_nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    imagen VARCHAR(255) NOT NULL DEFAULT 'img/doctor1.jpg',
    experiencia_anios INT NOT NULL DEFAULT 5,
    anios_clinica INT NOT NULL DEFAULT 2,
    consultas_realizadas INT NOT NULL DEFAULT 100,
    telefono CHAR(9) NULL,
    turno_manana TINYINT(1) NOT NULL DEFAULT 1,
    turno_tarde TINYINT(1) NOT NULL DEFAULT 1,
    dias_atencion VARCHAR(120) NOT NULL DEFAULT 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado',
    hora_inicio_manana TIME NOT NULL DEFAULT '08:00:00',
    hora_fin_manana TIME NOT NULL DEFAULT '13:00:00',
    hora_inicio_tarde TIME NOT NULL DEFAULT '14:00:00',
    hora_fin_tarde TIME NOT NULL DEFAULT '19:00:00',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_medico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_medico_esp FOREIGN KEY (especialidad_id) REFERENCES especialidades(id)
) ENGINE=InnoDB;

CREATE TABLE medico_disponibilidad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medico_id INT NOT NULL,
    fecha DATE NOT NULL,
    turno ENUM('manana', 'tarde', 'completo') NOT NULL DEFAULT 'manana',
    hora_inicio TIME NOT NULL DEFAULT '08:00:00',
    hora_fin TIME NOT NULL DEFAULT '13:00:00',
    estado ENUM('disponible', 'bloqueado', 'ausencia') NOT NULL DEFAULT 'disponible',
    motivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_disp_medico FOREIGN KEY (medico_id) REFERENCES medicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cita VARCHAR(20) NOT NULL UNIQUE,
    paciente_id INT NULL,
    usuario_id INT NULL,
    dni_paciente CHAR(8) NOT NULL,
    nombre_paciente VARCHAR(150) NOT NULL,
    telefono_paciente CHAR(9) NOT NULL,
    email_paciente VARCHAR(150) NOT NULL,
    especialidad_id INT NOT NULL,
    medico_id INT NOT NULL,
    fecha_cita DATE NOT NULL,
    fecha_fin_rango DATE NULL,
    hora_cita TIME NOT NULL DEFAULT '09:00:00',
    turno ENUM('manana', 'tarde', 'ambos') NOT NULL DEFAULT 'manana',
    estado ENUM('programada', 'reprogramada', 'en_atencion', 'finalizada', 'cancelada', 'no_show') NOT NULL DEFAULT 'programada',
    motivo_consulta VARCHAR(255) NULL,
    observaciones_atencion TEXT NULL,
    derivada_de_cita_id INT NULL,
    penalidad_aplicada DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notificacion_enviada TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cita_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_cita_esp FOREIGN KEY (especialidad_id) REFERENCES especialidades(id),
    CONSTRAINT fk_cita_medico FOREIGN KEY (medico_id) REFERENCES medicos(id)
) ENGINE=InnoDB;

CREATE TABLE historias_clinicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    dni_paciente CHAR(8) NOT NULL,
    cita_id INT NULL,
    medico_id INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    anamnesis TEXT NOT NULL,
    examen_fisico TEXT NULL,
    signos_vitales VARCHAR(255) NULL,
    diagnostico_cie10 VARCHAR(50) NULL,
    diagnostico TEXT NOT NULL,
    tratamiento TEXT NOT NULL,
    evolucion TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hc_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_hc_medico FOREIGN KEY (medico_id) REFERENCES medicos(id)
) ENGINE=InnoDB;

CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_receta VARCHAR(25) NOT NULL UNIQUE,
    cita_id INT NULL,
    paciente_id INT NOT NULL,
    dni_paciente CHAR(8) NOT NULL,
    medico_id INT NOT NULL,
    diagnostico VARCHAR(255) NOT NULL,
    medicamentos_json TEXT NOT NULL,
    indicaciones TEXT NOT NULL,
    estado ENUM('vigente', 'dispensada', 'vencida') NOT NULL DEFAULT 'vigente',
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receta_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_receta_medico FOREIGN KEY (medico_id) REFERENCES medicos(id)
) ENGINE=InnoDB;

CREATE TABLE productos_farmacia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 50,
    requiere_receta TINYINT(1) NOT NULL DEFAULT 0,
    imagen VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE pedidos_farmacia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_boleta VARCHAR(30) NOT NULL UNIQUE,
    usuario_id INT NULL,
    dni_cliente CHAR(8) NOT NULL,
    nombre_cliente VARCHAR(150) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    descuento_eps DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_pagado DECIMAL(10,2) NOT NULL,
    monto_no_cubierto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago VARCHAR(40) NOT NULL DEFAULT 'tarjeta_visa',
    codigo_receta VARCHAR(50) NULL,
    estado ENUM('pagado', 'entregado', 'anulado') NOT NULL DEFAULT 'pagado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE pedido_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    producto_id INT NOT NULL,
    nombre_producto VARCHAR(150) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos_farmacia(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_producto FOREIGN KEY (producto_id) REFERENCES productos_farmacia(id)
) ENGINE=InnoDB;

CREATE TABLE catalogo_laboratorio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    preparacion TEXT NOT NULL,
    tiempo_resultados VARCHAR(120) NOT NULL,
    valores_referencia TEXT NULL,
    imagen VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE ordenes_laboratorio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_orden VARCHAR(25) NOT NULL UNIQUE,
    cita_id INT NULL,
    paciente_id INT NULL,
    dni_paciente CHAR(8) NOT NULL,
    nombre_paciente VARCHAR(150) NOT NULL,
    medico_id INT NULL,
    analisis_json TEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago VARCHAR(40) NOT NULL DEFAULT 'tarjeta_visa',
    codigo_boleta VARCHAR(30) NULL,
    estado ENUM('pendiente_muestra', 'en_proceso', 'publicado') NOT NULL DEFAULT 'pendiente_muestra',
    resultado_resumen TEXT NULL,
    valores_resultado_json TEXT NULL,
    observaciones_lab TEXT NULL,
    fecha_publicacion DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE campanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    icono VARCHAR(60) NOT NULL DEFAULT 'fas fa-heartbeat',
    rango_fechas_texto VARCHAR(80) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    cupos_totales INT NOT NULL DEFAULT 500,
    cupos_ocupados INT NOT NULL DEFAULT 0,
    estado ENUM('activa', 'pronto', 'finalizada') NOT NULL DEFAULT 'activa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE campana_inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campana_id INT NOT NULL,
    dni CHAR(8) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_campana_dni (campana_id, dni),
    CONSTRAINT fk_insc_campana FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE preguntas_faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    pregunta TEXT NOT NULL,
    respuesta TEXT NULL,
    estado ENUM('pendiente', 'respondida') NOT NULL DEFAULT 'pendiente',
    respondido_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    respondido_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE contactos_afiliacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dni CHAR(8) NOT NULL,
    celular CHAR(9) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    acepto_privacidad TINYINT(1) NOT NULL DEFAULT 1,
    estado ENUM('ENVIADO', 'EN_SEGUIMIENTO', 'AFILIADO', 'DESCARTADO') NOT NULL DEFAULT 'ENVIADO',
    notas_admin VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destinatario VARCHAR(150) NOT NULL,
    canal ENUM('email', 'whatsapp', 'ambos') NOT NULL DEFAULT 'ambos',
    asunto VARCHAR(180) NOT NULL,
    mensaje TEXT NOT NULL,
    referencia_tipo VARCHAR(50) NOT NULL,
    referencia_id INT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'ENTREGADO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE auditoria_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario_email VARCHAR(150) NULL,
    rol VARCHAR(30) NULL,
    accion VARCHAR(80) NOT NULL,
    recurso VARCHAR(80) NOT NULL,
    recurso_id VARCHAR(50) NULL,
    detalle TEXT NULL,
    ip_address VARCHAR(45) NULL,
    http_status INT NOT NULL DEFAULT 200,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DATOS DE PRUEBA (SEED DATA)
-- Contraseñas:
--   Admins:    Admin123!
--   Médicos:   Medico123!
--   Pacientes: Paciente123!
-- ============================================================

INSERT INTO usuarios (id, email, password_hash, nombre, rol, google_id, avatar_url) VALUES
-- 2 Administradores de prueba
(1, 'admin1@centenario.pe', '$2y$12$hmbIxIdCT2FYUoDTJ1kq..//ug/KiMeJRhjpkw05zD67oy7j1aoUC', 'Roberto Mendoza', 'admin', 'google_admin_1', 'img/logo.png'),
(2, 'admin2@centenario.pe', '$2y$12$hmbIxIdCT2FYUoDTJ1kq..//ug/KiMeJRhjpkw05zD67oy7j1aoUC', 'Lucía Vargas', 'admin', 'google_admin_2', 'img/logo.png'),
-- 5 Médicos de prueba
(3, 'juan.perez@centenario.pe', '$2y$12$tsTKZ3YK6SKCkoTD4/5JHubrJD4t6guIEtdxPXJjaqzujabN7FgcW', 'Dr. Juan Pérez', 'medico', 'google_medico_1', 'img/doctor1.jpg'),
(4, 'maria.rodriguez@centenario.pe', '$2y$12$tsTKZ3YK6SKCkoTD4/5JHubrJD4t6guIEtdxPXJjaqzujabN7FgcW', 'Dra. María Rodríguez', 'medico', 'google_medico_2', 'img/doctor2.jpg'),
(5, 'carlos.gomez@centenario.pe', '$2y$12$tsTKZ3YK6SKCkoTD4/5JHubrJD4t6guIEtdxPXJjaqzujabN7FgcW', 'Dr. Carlos Gómez', 'medico', 'google_medico_3', 'img/doctor3.jpg'),
(6, 'ana.lopez@centenario.pe', '$2y$12$tsTKZ3YK6SKCkoTD4/5JHubrJD4t6guIEtdxPXJjaqzujabN7FgcW', 'Dra. Ana López', 'medico', 'google_medico_4', 'img/doctor4.jpg'),
(7, 'luis.martinez@centenario.pe', '$2y$12$tsTKZ3YK6SKCkoTD4/5JHubrJD4t6guIEtdxPXJjaqzujabN7FgcW', 'Dr. Luis Martínez', 'medico', 'google_medico_5', 'img/doctor5.jpg'),
-- 5 Pacientes de prueba
(8, 'carlos.ramirez@gmail.com', '$2y$12$cDHpXNIhtBsAJdhjx5Za7edNB7lvA2ghZqAlVElZdXC35H3HBYTq2', 'Carlos Alberto Ramírez Soto', 'paciente', 'google_paciente_1', NULL),
(9, 'elena.castro@gmail.com', '$2y$12$cDHpXNIhtBsAJdhjx5Za7edNB7lvA2ghZqAlVElZdXC35H3HBYTq2', 'Elena Patricia Castro Vera', 'paciente', 'google_paciente_2', NULL),
(10, 'jorge.chavez@gmail.com', '$2y$12$cDHpXNIhtBsAJdhjx5Za7edNB7lvA2ghZqAlVElZdXC35H3HBYTq2', 'Jorge Luis Chávez Díaz', 'paciente', 'google_paciente_3', NULL),
(11, 'valeria.rios@gmail.com', '$2y$12$cDHpXNIhtBsAJdhjx5Za7edNB7lvA2ghZqAlVElZdXC35H3HBYTq2', 'Valeria Fernanda Ríos Mendoza', 'paciente', 'google_paciente_4', NULL),
(12, 'diego.paredes@gmail.com', '$2y$12$cDHpXNIhtBsAJdhjx5Za7edNB7lvA2ghZqAlVElZdXC35H3HBYTq2', 'Diego Alejandro Paredes Silva', 'paciente', 'google_paciente_5', NULL);

-- Especialidades (Catálogo completo de la clínica)
INSERT INTO especialidades (id, clave, nombre, icono, descripcion, servicios_json, precio_consulta) VALUES
(1, 'cardio', 'Cardiología', 'fa-heart', 'Prevención, diagnóstico y tratamiento integral de enfermedades cardiovasculares y control hemodinámico.', '["Chequeos cardiológicos completos","Electrocardiograma digital","Control de presión arterial y arritmias","Ecocardiografía Doppler"]', 120.00),
(2, 'obstre', 'Obstetricia', 'fa-person-pregnant', 'Cuidado integral del embarazo, control prenatal, psicoprofilaxis y atención del parto.', '["Control prenatal mensual","Monitoreo fetal electrónico","Psicoprofilaxis obstétrica","Planificación familiar"]', 100.00),
(3, 'general', 'Medicina General', 'fa-user-doctor', 'Atención primaria integral, triaje clínico, prevención y derivación oportuna a especialistas.', '["Consulta médica integral","Certificados de salud","Control de enfermedades crónicas","Derivación a especialidades"]', 70.00),
(4, 'pedia', 'Pediatría', 'fa-baby', 'Atención médica especializada para recién nacidos, niños y adolescentes en crecimiento.', '["Control de niño sano (CRED)","Esquema de vacunación","Evaluación nutricional infantil","Urgencias pediátricas"]', 95.00),
(5, 'gineco', 'Ginecología', 'fa-venus', 'Salud integral y reproductiva femenina en todas las etapas de la vida.', '["Chequeo ginecológico preventivo","Papanicolaou y colposcopía","Ecografía pélvica y transvaginal","Manejo de climaterio"]', 110.00),
(6, 'labo', 'Laboratorio', 'fa-flask-vial', 'Análisis clínicos automatizados, hematología, bioquímica y microbiología con resultados en 24h.', '["Hemograma completo","Perfil lipídico y hepático","Descarte de diabetes","Urocultivo y microbiología"]', 50.00),
(7, 'analisis', 'Análisis', 'fa-microscope', 'Interpretación diagnóstica avanzada, anatomía patológica, genética e imágenes.', '["Lectura de resultados complejos","Biopsias e histopatología","Pruebas de ADN","Interconsulta diagnóstica"]', 90.00),
(8, 'odontologia', 'Odontología', 'fa-tooth', 'Salud bucal, prevención de caries, ortodoncia y estética dental.', '["Profilaxis y destartraje","Ortodoncia correctiva","Rehabilitación oral","Endodoncia"]', 85.00),
(9, 'psicologia', 'Psicología', 'fa-brain', 'Bienestar emocional, psicoterapia individual y manejo del estrés.', '["Terapia cognitivo-conductual","Manejo de ansiedad y estrés","Orientación familiar","Evaluación psicológica"]', 80.00),
(10, 'dermatologia', 'Dermatología', 'fa-hand-sparkles', 'Diagnóstico y cuidado médico de la piel, cabello y uñas.', '["Tratamiento de acné y rosácea","Mapeo de lunares","Dermatología clínica","Biopsia cutánea"]', 110.00),
(11, 'oftalmologia', 'Oftalmología', 'fa-eye', 'Salud visual, medición de agudeza y prevención de patologías oculares.', '["Medición computarizada de vista","Fondo de ojo","Tonometría ocular","Descarte de glaucoma y catarata"]', 100.00),
(12, 'neumologia', 'Neumología', 'fa-lungs', 'Prevención y tratamiento de enfermedades respiratorias y broncopulmonares.', '["Espirometría computarizada","Manejo de asma y EPOC","Evaluación post-infecciosa","Rehabilitación respiratoria"]', 115.00);

-- Médicos (Los 5 médicos con usuario + 4 médicos adicionales del staff = 9 médicos del catálogo)
INSERT INTO medicos (id, usuario_id, cmp, dni, nombre, especialidad_id, especialidad_nombre, descripcion, imagen, experiencia_anios, anios_clinica, consultas_realizadas, telefono, turno_manana, turno_tarde) VALUES
(1, 3, 'CMP-45101', '40112233', 'Dr. Juan Pérez', 1, 'Cardiología', 'Especialista en enfermedades del corazón, hipertensión arterial y rehabilitación cardiovascular.', 'img/doctor1.jpg', 15, 5, 1200, '991112233', 1, 1),
(2, 4, 'CMP-48202', '40223344', 'Dra. María Rodríguez', 2, 'Obstetricia', 'Especialista en cuidado integral del embarazo de alto riesgo, control prenatal y parto humanizado.', 'img/doctor2.jpg', 10, 3, 850, '992223344', 1, 1),
(3, 5, 'CMP-39303', '40334455', 'Dr. Carlos Gómez', 3, 'Medicina General', 'Médico internista y de atención primaria preventiva con enfoque en enfermedades metabólicas.', 'img/doctor3.jpg', 20, 12, 5000, '993334455', 1, 1),
(4, 6, 'CMP-51404', '40445566', 'Dra. Ana López', 4, 'Pediatría', 'Especialista en salud infantil, desarrollo psicomotor e inmunizaciones pediátricas.', 'img/doctor4.jpg', 8, 2, 600, '994445566', 1, 1),
(5, 7, 'CMP-46505', '40556677', 'Dr. Luis Martínez', 5, 'Ginecología', 'Especialista en salud reproductiva femenina, cirugía laparoscópica y patología cervical.', 'img/doctor5.jpg', 12, 6, 1500, '995556677', 1, 1),
(6, NULL, 'CMP-55606', '40667788', 'Dra. Sofía Torres', 6, 'Laboratorio', 'Médico patólogo clínico especialista en análisis clínicos y diagnósticos moleculares.', 'img/doctor6.jpg', 5, 1, 3000, '996667788', 1, 0),
(7, NULL, 'CMP-37707', '40778899', 'Dr. Pedro Sánchez', 7, 'Análisis', 'Especialista en interpretación avanzada de resultados médicos y diagnóstico anatomopatológico.', 'img/doctor7.jpg', 18, 10, 4200, '997778899', 0, 1),
(8, NULL, 'CMP-44808', '40889900', 'Dra. Elena Díaz', 1, 'Cardiología', 'Subespecialista en cardiología intervencionista, ecocardiografía y electrofisiología.', 'img/doctor8.jpg', 14, 4, 1100, '998889900', 1, 1),
(9, NULL, 'CMP-33909', '40990011', 'Dr. Miguel Ángel', 4, 'Pediatría', 'Especialista en neonatología, cuidados intensivos pediátricos y enfermedades respiratorias.', 'img/doctor9.jpg', 22, 15, 3500, '990001122', 1, 1);

-- 5 Pacientes de prueba con fichas médicas completas
INSERT INTO pacientes (id, usuario_id, dni, nombre, apellidos, edad, fecha_nacimiento, genero, pais, telefono, telefono_alt, email, direccion, departamento, provincia, distrito, nombre_emergencia, apellido_emergencia, parentesco_emergencia, telefono_emergencia, telefono_alt_emergencia, tipo_sangre, alergias, enfermedades, cirugias, tiene_seguro, aseguradora, numero_poliza, cobertura_eps_pct, deuda_pendiente, penalidad_noshow) VALUES
(1, 8, '71234561', 'Carlos Alberto', 'Ramírez Soto', 34, '1992-04-15', 'm', 'Peru', '987654321', '987654322', 'carlos.ramirez@gmail.com', 'Calle 5 de Marzo 420', 'la_libertad', 'Pacasmayo', 'Guadalupe', 'Rosa', 'Soto Vda. de Ramírez', 'madre', '944112233', '944112234', 'O+', 'Penicilina, Sulfas', 'Hipertensión arterial leve', 'Ninguna', 1, 'Rímac EPS', 'POL-889012', 80.00, 0.00, 0.00),
(2, 9, '72345672', 'Elena Patricia', 'Castro Vera', 28, '1998-08-22', 'f', 'Peru', '987111222', '987111223', 'elena.castro@gmail.com', 'Av. Grau 815', 'lambayeque', 'Chiclayo', 'Chiclayo', 'Miguel', 'Castro Paredes', 'padre', '955223344', '955223345', 'A+', 'Ibuprofeno, Polen', 'Ninguna', 'Cesárea segmentaria (2024)', 1, 'Pacífico EPS', 'POL-445120', 70.00, 0.00, 0.00),
(3, 10, '73456783', 'Jorge Luis', 'Chávez Díaz', 46, '1980-01-10', 'm', 'Peru', '986222333', '986222334', 'jorge.chavez@gmail.com', 'Jr. Unión 230', 'la_libertad', 'Trujillo', 'Trujillo', 'Carmen', 'Rojas de Chávez', 'pareja', '966334455', '966334456', 'B+', 'Ninguna', 'Diabetes Mellitus Tipo 2, Dislipidemia', 'Colecistectomía laparoscópica (2019)', 0, NULL, NULL, 0.00, 35.00, 20.00),
(4, 11, '74567894', 'Valeria Fernanda', 'Ríos Mendoza', 31, '1995-11-03', 'f', 'Peru', '985333444', '985333445', 'valeria.rios@gmail.com', 'Urb. Los Pinos Mz C Lt 12', 'lambayeque', 'Chiclayo', 'Pimentel', 'Andrés', 'Ríos Salazar', 'familiar', '977445566', '977445567', 'AB+', 'Contraste yodado', 'Hipotiroidismo en tratamiento', 'Apendicectomía (2022)', 1, 'Mapfre EPS', 'POL-331098', 75.00, 0.00, 0.00),
(5, 12, '75678905', 'Diego Alejandro', 'Paredes Silva', 25, '2001-06-19', 'm', 'Peru', '984444555', '984444556', 'diego.paredes@gmail.com', 'Av. Balta 1120', 'lambayeque', 'Lambayeque', 'Lambayeque', 'Patricia', 'Silva Torres', 'madre', '988556677', '988556678', 'O-', 'Ácaros, Aspirina (AAS)', 'Asma bronquial intermitente', 'Ninguna', 1, 'La Positiva EPS', 'POL-992341', 60.00, 0.00, 0.00);

-- Disponibilidad y bloques de médicos
INSERT INTO medico_disponibilidad (medico_id, fecha, turno, hora_inicio, hora_fin, estado, motivo) VALUES
(1, CURDATE(), 'manana', '08:00:00', '13:00:00', 'disponible', 'Consultorio 101 - Cardiología'),
(1, CURDATE(), 'tarde', '14:00:00', '18:00:00', 'disponible', 'Consultorio 101 - Ecocardiografía'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'tarde', '15:00:00', '17:00:00', 'bloqueado', 'Junta Médica de Cardiología'),
(2, CURDATE(), 'manana', '08:00:00', '13:00:00', 'disponible', 'Consultorio 202 - Obstetricia'),
(3, CURDATE(), 'manana', '08:00:00', '13:00:00', 'disponible', 'Consultorio 105 - Medicina General'),
(4, CURDATE(), 'tarde', '14:00:00', '19:00:00', 'disponible', 'Consultorio 301 - Pediatría'),
(5, CURDATE(), 'manana', '08:00:00', '13:00:00', 'disponible', 'Consultorio 204 - Ginecología');

-- Citas médicas (del día de hoy, futuras >24h para reprogramar/cancelar, pasadas finalizadas y no-show)
INSERT INTO citas (id, codigo_cita, paciente_id, usuario_id, dni_paciente, nombre_paciente, telefono_paciente, email_paciente, especialidad_id, medico_id, fecha_cita, fecha_fin_rango, hora_cita, turno, estado, motivo_consulta, observaciones_atencion, derivada_de_cita_id, penalidad_aplicada) VALUES
(1, 'CIT-2026-0001', 1, 8, '71234561', 'Carlos Alberto Ramírez Soto', '987654321', 'carlos.ramirez@gmail.com', 1, 1, CURDATE(), CURDATE(), '09:30:00', 'manana', 'programada', 'Control de presión arterial y dolor torácico leve', NULL, NULL, 0.00),
(2, 'CIT-2026-0002', 2, 9, '72345672', 'Elena Patricia Castro Vera', '987111222', 'elena.castro@gmail.com', 2, 2, CURDATE(), CURDATE(), '10:30:00', 'manana', 'programada', 'Control prenatal segundo trimestre', NULL, NULL, 0.00),
(3, 'CIT-2026-0003', 3, 10, '73456783', 'Jorge Luis Chávez Díaz', '986222333', 'jorge.chavez@gmail.com', 3, 3, CURDATE(), CURDATE(), '11:15:00', 'manana', 'en_atencion', 'Chequeo de glucosa y ajuste de metformina', 'Paciente en consultorio 105, toma de funciones vitales completada.', NULL, 0.00),
(4, 'CIT-2026-0004', 4, 11, '74567894', 'Valeria Fernanda Ríos Mendoza', '985333444', 'valeria.rios@gmail.com', 5, 5, CURDATE(), CURDATE(), '12:00:00', 'manana', 'programada', 'Control anual ginecológico y ecografía', NULL, NULL, 0.00),
(5, 'CIT-2026-0005', 5, 12, '75678905', 'Diego Alejandro Paredes Silva', '984444555', 'diego.paredes@gmail.com', 4, 4, CURDATE(), CURDATE(), '15:30:00', 'tarde', 'programada', 'Evaluación de crisis bronquial y espirometría', NULL, NULL, 0.00),
(6, 'CIT-2026-0006', 1, 8, '71234561', 'Carlos Alberto Ramírez Soto', '987654321', 'carlos.ramirez@gmail.com', 3, 3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00', 'manana', 'programada', 'Evaluación integral preventiva anual', NULL, NULL, 0.00),
(7, 'CIT-2026-0007', 2, 9, '72345672', 'Elena Patricia Castro Vera', '987111222', 'elena.castro@gmail.com', 1, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), '16:00:00', 'tarde', 'programada', 'Riesgo quirúrgico obstétrico (Interconsulta)', NULL, 2, 0.00),
(8, 'CIT-2026-0008', 1, 8, '71234561', 'Carlos Alberto Ramírez Soto', '987654321', 'carlos.ramirez@gmail.com', 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00', 'manana', 'finalizada', 'Chequeo cardiológico inicial', 'Paciente estable. Se solicitó perfil lipídico y se indicó Losartán 50mg.', NULL, 0.00),
(9, 'CIT-2026-0009', 3, 10, '73456783', 'Jorge Luis Chávez Díaz', '986222333', 'jorge.chavez@gmail.com', 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:00:00', 'tarde', 'no_show', 'Evaluación cardiológica por diabetes', 'Paciente no asistió pasados los 15 minutos de tolerancia (Regla TyC).', NULL, 20.00);

-- Historias Clínicas registradas
INSERT INTO historias_clinicas (id, paciente_id, dni_paciente, cita_id, medico_id, motivo, anamnesis, examen_fisico, signos_vitales, diagnostico_cie10, diagnostico, tratamiento, evolucion) VALUES
(1, 1, '71234561', 8, 1, 'Cefalea occipital matutina y cifras tensionales elevadas', 'Paciente varón de 34 años refiere cefalea leve desde hace 2 semanas. Antecedente familiar de HTA.', 'Murmullo vesicular pasa bien en ambos campos pulmonares. Ruidos cardíacos rítmicos, no soplos.', 'PA: 145/92 mmHg | FC: 78 lpm | Temp: 36.6 °C | Peso: 79 kg | Talla: 1.74 m', 'I10', 'Hipertensión esencial (primaria) estadio I', 'Losartán 50mg 1 tab cada 24h, dieta hiposódica, control con Perfil Lipídico y Glucosa.', 'Favorable con adherencia al tratamiento.'),
(2, 2, '72345672', NULL, 2, 'Control prenatal 20 semanas', 'Gestante segundigesta de 20 semanas acude a control prenatal. Movimientos fetales presentes, niega pérdidas.', 'Altura uterina 19 cm, LCF: 144 lpm, feto único activo.', 'PA: 110/70 mmHg | FC: 80 lpm | Temp: 36.5 °C | Peso: 64 kg | Talla: 1.62 m', 'Z34.8', 'Supervisión de embarazo normal de 20 semanas', 'Sulfato ferroso + Ácido fólico 1 tab/día, calcio 500mg/día, ecografía morfológica.', 'Embarazo normoevolutivo.'),
(3, 3, '73456783', 3, 3, 'Polidipsia y control metabólico de Diabetes Tipo 2', 'Paciente de 46 años con diagnóstico de DM2 hace 3 años. Refiere cumplimiento irregular de dieta.', 'Abdomen blando depresible, no visceromegalias. Pulsos periféricos conservados.', 'PA: 132/84 mmHg | FC: 82 lpm | Temp: 36.7 °C | Peso: 86 kg | Talla: 1.70 m', 'E11.9', 'Diabetes mellitus no insulinodependiente sin mención de complicación', 'Metformina 850mg con almuerzo y cena, control de Glucosa basal y Perfil Lipídico.', 'En seguimiento estrecho.'),
(4, 4, '74567894', NULL, 5, 'Chequeo preventivo ginecológico anual', 'Paciente mujer de 31 años acude para control anual y toma de Papanicolaou.', 'Especuloscopía: cérvix epitelizado, sin lesiones macroscópicas.', 'PA: 115/75 mmHg | FC: 72 lpm | Temp: 36.4 °C | Peso: 58 kg | Talla: 1.65 m', 'Z01.4', 'Examen ginecológico general (de rutina)', 'Vitamina C 1000mg suplementación, control citológico anual.', 'Estable, sin hallazgos patológicos.'),
(5, 5, '75678905', NULL, 4, 'Tos nocturna y sibilancias leves al esfuerzo', 'Paciente con antecedente de asma leve refiere opresión torácica ante cambios de clima.', 'Sibilancias espiratorias aisladas en bases pulmonares. SatO2 97%.', 'PA: 118/74 mmHg | FC: 76 lpm | Temp: 36.6 °C | Peso: 68 kg | Talla: 1.72 m', 'J45.0', 'Asma predominantemente alérgica', 'Jarabe broncodilatador y control ambiental de alérgenos. Uso de mascarilla KN95 en ambientes polvorientos.', 'Mejoría clínica progresiva.');

-- Recetas digitales emitidas (incluye recetas con Amoxicilina 500mg para probar validación en Farmacia RF-C09)
INSERT INTO recetas (id, codigo_receta, cita_id, paciente_id, dni_paciente, medico_id, diagnostico, medicamentos_json, indicaciones, estado, fecha_emision, fecha_vencimiento) VALUES
(1, 'REC-2026-001', 8, 1, '71234561', 1, 'Infección respiratoria bacteriana concomitante e HTA', '[{"producto_id":2,"nombre":"Amoxicilina 500mg","dosis":"1 cápsula cada 8 horas","duracion":"7 días","cantidad":21},{"producto_id":1,"nombre":"Paracetamol 500mg","dosis":"1 tableta cada 8 horas si hay fiebre o dolor","duracion":"5 días","cantidad":15}]', 'Tomar Amoxicilina 500mg puntualmente cada 8 horas después de los alimentos. Abundante líquido.', 'vigente', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY)),
(2, 'REC-2026-002', NULL, 2, '72345672', 2, 'Suplementación e infección urinaria baja en gestante', '[{"producto_id":2,"nombre":"Amoxicilina 500mg","dosis":"1 cápsula cada 8 horas","duracion":"7 días","cantidad":21},{"producto_id":4,"nombre":"Vitamina C 1000mg","dosis":"1 tableta al día","duracion":"30 días","cantidad":1}]', 'Completar los 7 días de antibiótico y realizar urocultivo de control.', 'vigente', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY)),
(3, 'REC-2026-003', 3, 3, '73456783', 3, 'Faringoamigdalitis aguda y dolor articular', '[{"producto_id":3,"nombre":"Ibuprofeno 400mg","dosis":"1 tableta cada 12 horas","duracion":"4 días","cantidad":8},{"producto_id":7,"nombre":"Jarabe para la Tos","dosis":"10 ml cada 8 horas","duracion":"5 días","cantidad":1}]', 'Evitar bebidas heladas y acudir a control en 5 días.', 'vigente', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY));

-- Productos de Farmacia (los 8 productos del catálogo original con control de stock y receta obligatoria en Amoxicilina)
INSERT INTO productos_farmacia (id, nombre, categoria, precio, stock, requiere_receta, imagen) VALUES
(1, 'Paracetamol 500mg', 'Analgésicos', 5.00, 120, 0, 'img/paracetamol.jpg'),
(2, 'Amoxicilina 500mg', 'Antibióticos', 15.00, 60, 1, 'img/amoxicilina.jpeg'),
(3, 'Ibuprofeno 400mg', 'Antiinflamatorios', 8.50, 95, 0, 'img/ibuprofeno.jpg'),
(4, 'Vitamina C 1000mg', 'Vitaminas', 25.00, 80, 0, 'img/vitaminaC.jpg'),
(5, 'Alcohol 70°', 'Primeros Auxilios', 7.00, 150, 0, 'img/alcohol.jpeg'),
(6, 'Mascarilla KN95', 'Protección', 3.50, 300, 0, 'img/mascarilla.jpg'),
(7, 'Jarabe para la Tos', 'Respiratorio', 18.00, 45, 0, 'img/jarabe.jpeg'),
(8, 'Protector Solar SPF 50', 'Cuidado de la Piel', 45.00, 40, 0, 'img/protectorSolar.png');

-- Pedidos de prueba en Farmacia
INSERT INTO pedidos_farmacia (id, codigo_boleta, usuario_id, dni_cliente, nombre_cliente, subtotal, descuento_eps, total_pagado, monto_no_cubierto, metodo_pago, codigo_receta, estado) VALUES
(1, 'BOL-F2026-0001', 8, '71234561', 'Carlos Alberto Ramírez Soto', 35.00, 28.00, 7.00, 7.00, 'tarjeta_visa', 'REC-2026-001', 'pagado'),
(2, 'BOL-F2026-0002', 9, '72345672', 'Elena Patricia Castro Vera', 52.00, 0.00, 52.00, 0.00, 'yape', NULL, 'pagado');

INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal) VALUES
(1, 2, 'Amoxicilina 500mg', 2, 15.00, 30.00),
(1, 1, 'Paracetamol 500mg', 1, 5.00, 5.00),
(2, 8, 'Protector Solar SPF 50', 1, 45.00, 45.00),
(2, 5, 'Alcohol 70°', 1, 7.00, 7.00);

-- Catálogo de Laboratorio (los 8 análisis originales)
INSERT INTO catalogo_laboratorio (id, nombre, descripcion, precio, preparacion, tiempo_resultados, valores_referencia, imagen) VALUES
(1, 'Análisis de Sangre', 'Este análisis básico permite detectar indicadores clave de funciones orgánicas, niveles de nutrientes e posibles infecciones.', 40.00, 'Requiere un ayuno de 8 a 12 horas (solo agua permitida).', 'Dentro de 24 - 48 horas.', 'Hemoglobina: 13.5-17.5 g/dL (H), 12.0-16.0 g/dL (M); Leucocitos: 4,500-11,000 /mm3', 'img/sangre.jpg'),
(2, 'Perfil Lipídico', 'Mide los niveles de grasas en la sangre, incluyendo colesterol total, LDL, HDL y triglicéridos. Es crucial para evaluar el riesgo de enfermedad cardiovascular.', 50.00, 'Requiere estrictamente un ayuno de 9 a 12 horas antes de la toma de muestra para obtener resultados precisos de los triglicéridos.', 'Dentro de 24 - 48 horas.', 'Colesterol Total < 200 mg/dL; LDL < 100 mg/dL; HDL > 40 mg/dL; Triglicéridos < 150 mg/dL', 'img/Perfil-Lipidico.png'),
(3, 'Biopsia', 'Es la extracción de una pequeña muestra de tejido para su análisis microscópico. Es la más precisa para confirmar o descartar enfermedades como el cáncer.', 300.00, 'Depende del tipo de procedimiento.', '3 a 7 días hábiles (puede variar según la complejidad del análisis histopatológico).', 'Estudio histopatológico libre de atipia celular maligna', 'img/biopsia.jpg'),
(4, 'Glucosa', 'Mide la cantidad de azúcar (glucosa) presente en la sangre. Es la prueba estándar para el diagnóstico y seguimiento de la diabetes.', 30.00, 'Se requiere un ayuno estricto de 8 a 10 horas. Solo se permite la ingesta de agua.', 'El mismo día o 24 horas.', 'Normal: Menos de 100 mg/dL; Prediabetes: 100 mg/dL a 125 mg/dL; Diabetes: 126 mg/dL o superior.', 'img/glucosa.webp'),
(5, 'Orina Completa', 'Análisis físico, químico y microscópico de la orina. Útil para detectar infecciones, enfermedades renales y otros problemas metabólicos.', 25.00, 'No requiere ayuno. Se recomienda recolectar la primera orina de la mañana en recipiente estéril.', 'Dentro de 24 - 48 horas.', 'pH: 4.5-8.0; Densidad: 1.005-1.030; Leucocitos: 0-5 /campo; Nitritos: Negativo', 'img/analisis-de-orina.png'),
(6, 'Tomografía', 'Técnica de imagen médica que utiliza rayos X y computadoras para crear imágenes transversales detalladas del cuerpo.', 350.00, 'Con contraste: Se necesita un ayuno de 4 a 6 horas. Sin contraste: Generalmente no requiere ayuno.', 'Impresión inmediata; informe médico en 24 - 48 horas.', 'Estructuras anatómicas conservadas sin lesiones ocupantes de espacio', 'img/tomografia.jpg'),
(7, 'ADN', 'Análisis genético para identificar la composición única de su ADN. Se usa para pruebas de paternidad e identificar riesgos de enfermedades hereditarias.', 700.00, 'Las pruebas de saliva o hisopado bucal solo exigen no comer, beber, fumar o masticar chicle 30 minutos antes de la toma de muestra.', '1 a 6 semanas, según la complejidad del análisis de laboratorio requerido.', 'Concordancia alélica > 99.99% en marcadores STR analizados', 'img/ADN.jpg'),
(8, 'Hematología', 'Estudio de la sangre (eritrocitos), glóbulos blancos (leucocitos) y plaquetas. Es crucial para diagnosticar anemia, leucemia y problemas de coagulación.', 50.00, 'Generalmente, se requiere un ayuno de 4 a 8 horas.', 'Dentro de 24 horas o menos.', 'Hematocrito: 40-50%; Plaquetas: 150,000-450,000 /mm3; VCM: 80-100 fL', 'img/hematologia.jpg');

-- Órdenes y Resultados de Laboratorio (con códigos listos para consultar en RF-C10 y RF-M06)
INSERT INTO ordenes_laboratorio (id, codigo_orden, cita_id, paciente_id, dni_paciente, nombre_paciente, medico_id, analisis_json, total, metodo_pago, codigo_boleta, estado, resultado_resumen, valores_resultado_json, observaciones_lab, fecha_publicacion) VALUES
(1, 'LAB-2026-001', 8, 1, '71234561', 'Carlos Alberto Ramírez Soto', 1, '[{"nombre":"Perfil Lipídico","precio":50.00,"cantidad":1},{"nombre":"Glucosa","precio":30.00,"cantidad":1}]', 80.00, 'tarjeta_visa', 'BOL-L2026-0001', 'publicado', 'Colesterol Total: 192 mg/dL (Normal) | LDL: 112 mg/dL (Levemente elevado) | HDL: 48 mg/dL | Triglicéridos: 145 mg/dL | Glucosa Basal: 94 mg/dL (Normal)', '[{"parametro":"Colesterol Total","valor":"192 mg/dL","referencia":"< 200 mg/dL","estado":"Normal"},{"parametro":"Colesterol LDL","valor":"112 mg/dL","referencia":"< 100 mg/dL","estado":"Observado"},{"parametro":"Colesterol HDL","valor":"48 mg/dL","referencia":"> 40 mg/dL","estado":"Normal"},{"parametro":"Triglicéridos","valor":"145 mg/dL","referencia":"< 150 mg/dL","estado":"Normal"},{"parametro":"Glucosa en ayunas","valor":"94 mg/dL","referencia":"70 - 99 mg/dL","estado":"Normal"}]', 'Muestra procesada en analizador automatizado Roche Cobas. Validado por Dra. Sofía Torres (CMP-55606).', NOW()),
(2, 'LAB-2026-002', 2, 2, '72345672', 'Elena Patricia Castro Vera', 2, '[{"nombre":"Hematología","precio":50.00,"cantidad":1},{"nombre":"Orina Completa","precio":25.00,"cantidad":1}]', 75.00, 'yape', 'BOL-L2026-0002', 'publicado', 'Hemoglobina: 12.4 g/dL | Plaquetas: 245,000 /mm3 | Examen de Orina: Leucocitos 2-4 x campo, Nitritos Negativo.', '[{"parametro":"Hemoglobina","valor":"12.4 g/dL","referencia":"12.0 - 16.0 g/dL","estado":"Normal"},{"parametro":"Plaquetas","valor":"245,000 /mm3","referencia":"150,000 - 450,000","estado":"Normal"},{"parametro":"Orina - Leucocitos","valor":"2-4 /campo","referencia":"0 - 5 /campo","estado":"Normal"},{"parametro":"Orina - Nitritos","valor":"Negativo","referencia":"Negativo","estado":"Normal"}]', 'Valores dentro de rangos fisiológicos para gestante del segundo trimestre.', NOW()),
(3, 'LAB-2026-003', 3, 3, '73456783', 'Jorge Luis Chávez Díaz', 3, '[{"nombre":"Glucosa","precio":30.00,"cantidad":1},{"nombre":"Perfil Lipídico","precio":50.00,"cantidad":1}]', 80.00, 'tarjeta_mastercard', 'BOL-L2026-0003', 'en_proceso', 'Muestra en procesamiento bioquímico', NULL, 'Toma de muestra en ayunas realizada a las 07:45 a.m.', NULL);

-- Campañas de Salud (3 activas con countdown futuro + 3 próximas)
INSERT INTO campanas (id, titulo, descripcion, icono, rango_fechas_texto, fecha_inicio, fecha_fin, cupos_totales, cupos_ocupados, estado) VALUES
(1, 'Chequeo Cardiológico', 'Descarte temprano de hipertensión arterial, arritmias y riesgo coronario con electrocardiograma incluido.', 'fas fa-heartbeat', '16 Dic - 22 Dic', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 1000, 14, 'activa'),
(2, 'Revisión Dental', 'Profilaxis dental, odontograma completo y fluorización preventiva para niños y adultos.', 'fas fa-tooth', '23 Dic - 29 Dic', NOW(), DATE_ADD(NOW(), INTERVAL 6 DAY), 800, 9, 'activa'),
(3, 'Detección de Diabetes', 'Dosaje gratuito de glucosa capilar, evaluación nutricional y consejería metabólica.', 'fas fa-pump-medical', '30 Dic - 05 Ene', NOW(), DATE_ADD(NOW(), INTERVAL 20 HOUR), 600, 22, 'activa'),
(4, 'Salud Visual', 'Medición computarizada de agudeza visual y descarte de fatiga visual digital.', 'fas fa-eye', '06 Ene - 12 Ene', DATE_ADD(NOW(), INTERVAL 10 DAY), NULL, 700, 0, 'pronto'),
(5, 'Control Pediátrico', 'Evaluación de crecimiento, desarrollo, descarte de anemia infantil y control de vacunas.', 'fas fa-child', '13 Ene - 20 Ene', DATE_ADD(NOW(), INTERVAL 18 DAY), NULL, 500, 0, 'pronto'),
(6, 'Revisión Pulmonar', 'Evaluación neumológica y espirometría preventiva ante cambios estacionales.', 'fas fa-lungs', '21 Ene - 02 Feb', DATE_ADD(NOW(), INTERVAL 25 DAY), NULL, 900, 0, 'pronto');

INSERT INTO campana_inscripciones (campana_id, dni, nombre, email, usuario_id) VALUES
(1, '71234561', 'Carlos Alberto Ramírez Soto', 'carlos.ramirez@gmail.com', 8),
(2, '72345672', 'Elena Patricia Castro Vera', 'elena.castro@gmail.com', 9),
(3, '73456783', 'Jorge Luis Chávez Díaz', 'jorge.chavez@gmail.com', 10);

-- Preguntas FAQ enviadas por usuarios
INSERT INTO preguntas_faq (nombre, email, pregunta, respuesta, estado, respondido_por, respondido_at) VALUES
('María Fernanda Salazar', 'mfsalazar@gmail.com', '¿Cuánto tiempo antes debo llegar para mi toma de muestra de perfil lipídico?', 'Debe acudir entre las 7:00 a.m. y 9:30 a.m. con un ayuno estricto de 9 a 12 horas (solo puede beber agua).', 'respondida', 1, NOW()),
('Ricardo Quispe Rojas', 'rquispe@hotmail.com', '¿Puedo comprar antibióticos en la farmacia virtual con una receta emitida en mi consulta?', NULL, 'pendiente', NULL, NULL);

-- Solicitudes de Afiliación / Contacto
INSERT INTO contactos_afiliacion (dni, celular, correo, acepto_privacidad, estado, notas_admin) VALUES
('71234561', '987654321', 'carlos.ramirez@gmail.com', 1, 'AFILIADO', 'Plan Familiar Centenario activado.'),
('45891230', '945678123', ' familia.torres@gmail.com', 1, 'ENVIADO', 'Solicita información de Plan Salud Adulto Mayor.');

-- Notificaciones enviadas (WhatsApp / Email)
INSERT INTO notificaciones (destinatario, canal, asunto, mensaje, referencia_tipo, referencia_id, estado) VALUES
('carlos.ramirez@gmail.com / 987654321', 'ambos', 'Confirmación de Cita CIT-2026-0001', 'Su cita en Cardiología con el Dr. Juan Pérez ha sido confirmada. Recuerde asistir 15 minutos antes.', 'cita', 1, 'ENTREGADO'),
('elena.castro@gmail.com / 987111222', 'ambos', 'Resultados de Laboratorio LAB-2026-002', 'Sus resultados de laboratorio con código LAB-2026-002 ya se encuentran disponibles para descarga.', 'laboratorio', 2, 'ENTREGADO');

-- Auditoría Ley 29733
INSERT INTO auditoria_logs (usuario_id, usuario_email, rol, accion, recurso, recurso_id, detalle, ip_address, http_status) VALUES
(3, 'juan.perez@centenario.pe', 'medico', 'LECTURA_HISTORIA_CLINICA', 'pacientes', '71234561', 'Acceso médico autorizado a historia clínica del paciente DNI 71234561', '127.0.0.1', 200),
(8, 'carlos.ramirez@gmail.com', 'paciente', 'CONSENTIMIENTO_LEY_29733', 'pacientes', '71234561', 'Aceptación expresa de Términos y Tratamiento de Datos Personales Ley N° 29733', '127.0.0.1', 201),
(1, 'admin1@centenario.pe', 'admin', 'PUBLICACION_RESULTADO_LAB', 'ordenes_laboratorio', 'LAB-2026-001', 'Validación y publicación de resultados de laboratorio LAB-2026-001', '127.0.0.1', 200);
