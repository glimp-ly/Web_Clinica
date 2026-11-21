<?php
declare(strict_types=1);

namespace App\Core;

interface ModuleRoutesInterface
{
    public function dispatch(Request $req): bool;
}
