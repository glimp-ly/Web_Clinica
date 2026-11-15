<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Response;

final class EspecialidadController
{
    public function index(): never
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM especialidades WHERE activo = 1 ORDER BY id ASC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['items'] = json_decode($r['servicios_json'], true) ?: [];
        }
        Response::json(['ok' => true, 'especialidades' => $rows]);
    }
}
