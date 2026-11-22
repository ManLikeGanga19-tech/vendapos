<?php

declare(strict_types=1);

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no env() calls outside config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('no dd or dump in production code')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsedIn('App');
