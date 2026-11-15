<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\JwtAuth;
use App\Core\Response;

/**
 * Reportes consolidados y trazabilidad para el panel administrativo.
 */
final class ReporteController
{
    public function resumen(): never
    {
        JwtAuth::requireAuth(['admin']);
        $db = Database::getConnection();

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

        Response::json([
            'ok' => true,
            'citas_resumen' => $citasResumen,
            'citas_por_especialidad' => $citasPorEsp,
            'ventas_farmacia' => $ventasFarmacia,
            'ventas_laboratorio' => $ventasLab,
            'ingresos_totales' => (float)($ventasFarmacia['ingresos_pagados'] ?? 0) + (float)($ventasLab['ingresos_lab'] ?? 0),
            'campanas' => $db->query('SELECT id, titulo, cupos_totales, cupos_ocupados, estado FROM campanas ORDER BY id ASC')->fetchAll(),
            'auditoria' => $db->query('SELECT * FROM auditoria_logs ORDER BY id DESC LIMIT 60')->fetchAll(),
            'notificaciones' => $db->query('SELECT * FROM notificaciones ORDER BY id DESC LIMIT 40')->fetchAll(),
            'usuarios' => $db->query('SELECT id, email, nombre, rol, google_id, activo, created_at FROM usuarios ORDER BY id ASC')->fetchAll(),
        ]);
    }
}
