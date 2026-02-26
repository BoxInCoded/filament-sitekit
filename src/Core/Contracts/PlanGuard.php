<?php

declare(strict_types=1);

namespace BoxInCoded\FilamentSiteKit\Core\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface PlanGuard
{
    public function canCreateAnotherAccount(?Authenticatable $user): bool;
}
