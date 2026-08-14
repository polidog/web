<?php

declare(strict_types=1);

use App\AppConfigurator;
use Polidog\Relayer\Relayer;

require_once __DIR__ . '/../vendor/autoload.php';

Relayer::boot(__DIR__ . '/..', new AppConfigurator(__DIR__ . '/..'))
    ->run();
