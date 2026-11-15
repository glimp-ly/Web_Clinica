<?php
declare(strict_types=1);

namespace App;

use App\Config\Database;
use App\Controllers\AtencionPublicaController;
use App\Controllers\AuthController;
use App\Controllers\CampanaController;
use App\Controllers\CitaController;
use App\Controllers\EspecialidadController;
use App\Controllers\FarmaciaController;
use App\Controllers\LaboratorioController;
use App\Controllers\MedicoController;
use App\Controllers\PacienteController;
use App\Controllers\PerfilController;
use App\Controllers\RecetaController;
use App\Controllers\ReporteController;
use App\Core\Request;
use App\Core\Response;

/**
 * Enrutador central de la API REST hacia sus controladores de dominio.
 */
final class Router
{
    public function dispatch(Request $req): never
    {
        if ($req->method === 'OPTIONS') {
            Response::sendHeaders();
            http_response_code(204);
            exit;
        }

        $method = $req->method;
        $route = str_replace('/campañas', '/campanas', $req->route);
        $segments = $req->segments;
        if (($segments[0] ?? '') === 'campañas') {
            $segments[0] = 'campanas';
        }

        $staticKey = $method . ' ' . $route;
        if ($this->dispatchStatic($staticKey, $req)) {
            exit;
        }

        $this->dispatchDynamic($method, $route, $segments, $req);
    }

    private function dispatchStatic(string $staticKey, Request $req): bool
    {
        switch ($staticKey) {
            case 'GET /':
            case 'GET /health':
                Database::getConnection()->query('SELECT 1');
                Response::json(['ok' => true, 'service' => 'Clinica Centenario API', 'status' => 'healthy']);
            case 'POST /auth/register':
                (new AuthController())->register($req);
            case 'POST /auth/login':
                (new AuthController())->login($req);
            case 'GET /auth/google-accounts':
                (new AuthController())->googleAccounts();
            case 'POST /auth/google':
                (new AuthController())->googleLogin($req);
            case 'POST /auth/forgot-password':
                (new AuthController())->forgotPassword($req);
            case 'POST /auth/reset-password':
                (new AuthController())->resetPassword($req);
            case 'POST /auth/logout':
                (new AuthController())->logout();
            case 'GET /auth/me':
                (new AuthController())->me();
            case 'GET /perfil':
                (new PerfilController())->show();
            case 'PUT /perfil':
                (new PerfilController())->update($req);
            case 'DELETE /perfil':
                (new PerfilController())->anonymize();
            case 'GET /pacientes':
                (new PacienteController())->index($req);
            case 'POST /pacientes':
                (new PacienteController())->store($req);
            case 'GET /especialidades':
                (new EspecialidadController())->index();
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
            case 'GET /citas/ocupadas':
                (new CitaController())->ocupadas($req);
            case 'GET /citas':
            case 'GET /mis-citas':
                (new CitaController())->index($req);
            case 'POST /citas':
                (new CitaController())->store($req);
            case 'GET /recetas':
                (new RecetaController())->index($req);
            case 'POST /recetas':
                (new RecetaController())->store($req);
            case 'GET /productos':
                (new FarmaciaController())->listProductos($req);
            case 'POST /productos':
                (new FarmaciaController())->storeProducto($req);
            case 'GET /pedidos':
                (new FarmaciaController())->listPedidos();
            case 'POST /pedidos':
                (new FarmaciaController())->storePedido($req);
            case 'GET /laboratorio/catalogo':
                (new LaboratorioController())->catalogo();
            case 'GET /ordenes-lab':
                (new LaboratorioController())->listOrdenes();
            case 'POST /ordenes-lab':
                (new LaboratorioController())->storeOrden($req);
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
            default:
                return false;
        }
    }

    /** @param list<string> $segments */
    private function dispatchDynamic(string $method, string $route, array $segments, Request $req): never
    {
        $count = count($segments);

        if ($count === 3 && $segments[0] === 'pacientes' && $segments[2] === 'historia') {
            (new PacienteController())->historia($req, $segments[1]);
        }
        if ($count === 3 && $segments[0] === 'medicos' && $segments[1] === 'mi-disponibilidad' && $method === 'DELETE') {
            (new MedicoController())->deleteDisponibilidad((int)$segments[2]);
        }
        if ($count === 2 && $segments[0] === 'medicos' && is_numeric($segments[1]) && ($method === 'PUT' || $method === 'DELETE')) {
            (new MedicoController())->updateOrDelete($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'citas' && is_numeric($segments[1]) && ($method === 'PUT' || $method === 'DELETE')) {
            (new CitaController())->updateOrDelete($req, (int)$segments[1]);
        }
        if ($count === 3 && $segments[0] === 'citas' && is_numeric($segments[1]) && $segments[2] === 'derivar' && $method === 'POST') {
            (new CitaController())->derivar($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'productos' && is_numeric($segments[1]) && $method === 'PUT') {
            (new FarmaciaController())->updateProducto($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'ordenes-lab' && is_numeric($segments[1]) && $method === 'PUT') {
            (new LaboratorioController())->updateOrden($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'resultados' && $method === 'GET') {
            (new LaboratorioController())->verResultado($req, $segments[1]);
        }
        if ($count === 2 && $segments[0] === 'campanas' && is_numeric($segments[1]) && $method === 'PUT') {
            (new CampanaController())->update($req, (int)$segments[1]);
        }
        if ($count === 3 && $segments[0] === 'campanas' && is_numeric($segments[1]) && $segments[2] === 'inscribir' && $method === 'POST') {
            (new CampanaController())->inscribir($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'preguntas' && is_numeric($segments[1]) && $method === 'PUT') {
            (new AtencionPublicaController())->responderPregunta($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'contactos' && is_numeric($segments[1]) && $method === 'PUT') {
            (new AtencionPublicaController())->updateContacto($req, (int)$segments[1]);
        }
        if ($method === 'GET' && str_starts_with($route, '/reportes')) {
            (new ReporteController())->resumen();
        }

        Response::error("Endpoint no encontrado: {$method} {$req->route}", 404);
    }
}
