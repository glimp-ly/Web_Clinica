<?php
declare(strict_types=1);

namespace App\Modules\Modulo4_FarmaciaLaboratorio;

use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Modules\Modulo4_FarmaciaLaboratorio\Controllers\FarmaciaController;
use App\Modules\Modulo4_FarmaciaLaboratorio\Controllers\LaboratorioController;

/**
 * Enrutador del Módulo 4: Farmacia Virtual, Pedidos/EPS, Laboratorio Clínico y Resultados.
 */
final class Routes implements ModuleRoutesInterface
{
    public function dispatch(Request $req): bool
    {
        switch ($req->method . ' ' . $req->route) {
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
        }

        $segments = $req->segments;
        $count = count($segments);

        if ($count === 2 && $segments[0] === 'productos' && is_numeric($segments[1]) && $req->method === 'PUT') {
            (new FarmaciaController())->updateProducto($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'ordenes-lab' && is_numeric($segments[1]) && $req->method === 'PUT') {
            (new LaboratorioController())->updateOrden($req, (int)$segments[1]);
        }
        if ($count === 2 && $segments[0] === 'resultados' && $req->method === 'GET') {
            (new LaboratorioController())->verResultado($req, $segments[1]);
        }

        return false;
    }
}
