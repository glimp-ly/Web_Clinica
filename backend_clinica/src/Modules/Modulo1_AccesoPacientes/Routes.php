<?php
declare(strict_types=1);

namespace App\Modules\Modulo1_AccesoPacientes;

use App\Core\ModuleRoutesInterface;
use App\Core\Request;
use App\Modules\Modulo1_AccesoPacientes\Controllers\AuthController;
use App\Modules\Modulo1_AccesoPacientes\Controllers\PacienteController;
use App\Modules\Modulo1_AccesoPacientes\Controllers\PerfilController;

/**
 * Enrutador del Módulo 1: Autenticación, Perfil/ARCO y Fichas de Pacientes.
 */
final class Routes implements ModuleRoutesInterface
{
    public function dispatch(Request $req): bool
    {
        switch ($req->method . ' ' . $req->route) {
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
            default:
                return false;
        }
    }
}
