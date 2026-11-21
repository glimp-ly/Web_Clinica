<?php
declare(strict_types=1);

namespace App;

use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Modulo1_AccesoPacientes\Routes as Modulo1Routes;
use App\Modules\Modulo2_CitasAgenda\Routes as Modulo2Routes;
use App\Modules\Modulo3_PortalMedico\Routes as Modulo3Routes;
use App\Modules\Modulo4_FarmaciaLaboratorio\Routes as Modulo4Routes;
use App\Modules\Modulo5_ComunidadAdmin\Routes as Modulo5Routes;

/**
 * Enrutador maestro que delega las peticiones REST a los 5 módulos del sistema.
 */
final class Router
{
    /** @return list<ModuleRoutesInterface> */
    private function modules(): array
    {
        return [
            new Modulo1Routes(),
            new Modulo2Routes(),
            new Modulo3Routes(),
            new Modulo4Routes(),
            new Modulo5Routes(),
        ];
    }

    public function dispatch(Request $req): never
    {
        if ($req->method === 'OPTIONS') {
            Response::sendHeaders();
            http_response_code(204);
            exit;
        }

        foreach ($this->modules() as $module) {
            if ($module->dispatch($req)) {
                exit;
            }
        }

        Response::error("Endpoint no encontrado: {$req->method} {$req->route}", 404);
    }
}
