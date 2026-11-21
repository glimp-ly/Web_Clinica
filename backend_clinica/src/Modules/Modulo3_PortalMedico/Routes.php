<?php
declare(strict_types=1);

namespace App\Modules\Modulo3_PortalMedico;

use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Modules\Modulo3_PortalMedico\Controllers\HistoriaClinicaController;
use App\Modules\Modulo3_PortalMedico\Controllers\MedicoController;
use App\Modules\Modulo3_PortalMedico\Controllers\RecetaController;

/**
 * Enrutador del Módulo 3: Portal Médico, Staff, Disponibilidad, Historia Clínica y Recetas.
 */
final class Routes implements ModuleRoutesInterface
{
    public function dispatch(Request $req): bool
    {
        switch ($req->method . ' ' . $req->route) {
            case 'GET /medicos/mi-resumen':
                (new MedicoController())->miResumen($req);
            case 'GET /medicos/mi-disponibilidad':
            case 'PUT /medicos/mi-disponibilidad':
            case 'POST /medicos/mi-disponibilidad':
                (new MedicoController())->disponibilidad($req);
            case 'GET /medicos':
                (new MedicoController())->index($req);
            case 'POST /medicos':
                (new MedicoController())->store($req);
            case 'GET /recetas':
                (new RecetaController())->index($req);
            case 'POST /recetas':
                (new RecetaController())->store($req);
        }

        $segments = $req->segments;
        $count = count($segments);

        if ($count === 3 && $segments[0] === 'pacientes' && $segments[2] === 'historia') {
            (new HistoriaClinicaController())->historia($req, $segments[1]);
        }
        if ($count === 3 && $segments[0] === 'medicos' && $segments[1] === 'mi-disponibilidad' && $req->method === 'DELETE') {
            (new MedicoController())->deleteDisponibilidad((int)$segments[2]);
        }
        if ($count === 2 && $segments[0] === 'medicos' && is_numeric($segments[1]) && ($req->method === 'PUT' || $req->method === 'DELETE')) {
            (new MedicoController())->updateOrDelete($req, (int)$segments[1]);
        }

        return false;
    }
}
