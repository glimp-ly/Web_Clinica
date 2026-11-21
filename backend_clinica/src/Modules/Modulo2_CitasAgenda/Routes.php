<?php
declare(strict_types=1);

namespace App\Modules\Modulo2_CitasAgenda;

use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Modules\Modulo2_CitasAgenda\Controllers\CitaController;
use App\Modules\Modulo2_CitasAgenda\Controllers\EspecialidadController;

/**
 * Enrutador del Módulo 2: Especialidades, Disponibilidad Horaria y Citas Médicas.
 */
final class Routes implements ModuleRoutesInterface
{
    public function dispatch(Request $req): bool
    {
        switch ($req->method . ' ' . $req->route) {
            case 'GET /especialidades':
                (new EspecialidadController())->index();
            case 'GET /citas/ocupadas':
                (new CitaController())->ocupadas($req);
            case 'GET /citas':
            case 'GET /mis-citas':
                (new CitaController())->index($req);
            case 'POST /citas':
                (new CitaController())->store($req);
        }

        $segments = $req->segments;
        $count = count($segments);

        if ($count === 2 && $segments[0] === 'citas' && is_numeric($segments[1]) && ($req->method === 'PUT' || $req->method === 'DELETE')) {
            (new CitaController())->updateOrDelete($req, (int)$segments[1]);
        }
        if ($count === 3 && $segments[0] === 'citas' && is_numeric($segments[1]) && $segments[2] === 'derivar' && $req->method === 'POST') {
            (new CitaController())->derivar($req, (int)$segments[1]);
        }

        return false;
    }
}
