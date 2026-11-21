<?php
declare(strict_types=1);

namespace App\Modules\Modulo5_ComunidadAdmin;

use App\Config\Database;
use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Modulo5_ComunidadAdmin\Controllers\AtencionPublicaController;
use App\Modules\Modulo5_ComunidadAdmin\Controllers\CampanaController;
use App\Modules\Modulo5_ComunidadAdmin\Controllers\ReporteController;

/**
 * Enrutador del Módulo 5: Campañas de Salud, FAQ, Afiliaciones, Reportes Admin y Healthcheck.
 */
final class Routes implements ModuleRoutesInterface
{
    public function dispatch(Request $req): bool
    {
        $route = str_replace('/campañas', '/campanas', $req->route);
        $segments = $req->segments;
        if (($segments[0] ?? '') === 'campañas') {
            $segments[0] = 'campanas';
        }

        switch ($req->method . ' ' . $route) {
            case 'GET /':
            case 'GET /health':
                Database::getConnection()->query('SELECT 1');
                Response::json(['ok' => true, 'service' => 'Clinica Centenario API', 'status' => 'healthy']);
            case 'GET /campanas':
                (new CampanaController())->index();
            case 'POST /campanas':
                (new CampanaController())->store($req);
            case 'GET /preguntas':
                (new AtencionPublicaController())->listPreguntas();
            case 'POST /preguntas':
                (new AtencionPublicaController())->storePregunta($req);
            case 'GET /contactos':
                (new AtencionPublicaController())->listContactos();
            case 'POST /contactos':
                (new AtencionPublicaController())->storeContacto($req);
        }

        $count = count($segments);

        if ($count === 2 && $segments[0] === 'campanas' && is_numeric($segments[1]) && $req->method === 'PUT') {
            (new CampanaController())->update($req, (int)$segments[1]);
        }
        if ($count === 3 && $segments[0] === 'campanas' && is_numeric($segments[1]) && $segments[2] === 'inscribir' && $req->method === 'POST') {
            (new CampanaController())->inscribir($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'preguntas' && is_numeric($segments[1]) && $req->method === 'PUT') {
            (new AtencionPublicaController())->responderPregunta($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'contactos' && is_numeric($segments[1]) && $req->method === 'PUT') {
            (new AtencionPublicaController())->updateContacto($req, (int)$segments[1]);
        }
        if ($req->method === 'GET' && str_starts_with($route, '/reportes')) {
            (new ReporteController())->resumen();
        }

        return false;
    }
}
