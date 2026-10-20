<?php

declare(strict_types=1);

arch('domain models extend eloquent')
    ->expect('App\Domain')
    ->toBeClasses();

arch('controllers depend on domain, not on infrastructure')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('models are not used in routes')
    ->expect('App\Models')
    ->not->toBeUsedIn('routes');

arch('value objects are final and immutable')
    ->expect('VendaPOS\Core\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('enums are strict types')
    ->expect('VendaPOS\Core\Enums')
    ->toBeEnums();

arch('no env() calls outside config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('no dd or dump in production code')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsedIn('App');

arch('filament resource pages extend Filament page classes')
    ->expect('App\Filament\Resources\TenantResource\Pages')
    ->toBeClasses();
