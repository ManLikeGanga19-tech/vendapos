<?php

declare(strict_types=1);

/*
| CLAUDE.md §3.4: modules under app/Domain talk to each other through Actions,
| events or contracts, never through another module's Eloquent models.
*/
$modules = array_values(array_filter(
    scandir(__DIR__.'/../../app/Domain') ?: [],
    fn (string $entry) => $entry[0] !== '.' && is_dir(__DIR__.'/../../app/Domain/'.$entry),
));

foreach ($modules as $module) {
    $otherModels = array_map(
        fn (string $other) => "App\\Domain\\{$other}\\Models",
        array_values(array_diff($modules, [$module])),
    );

    if ($otherModels === []) {
        continue;
    }

    arch("{$module} does not reach into other modules' models")
        ->expect("App\\Domain\\{$module}")
        ->not->toUse($otherModels);
}
