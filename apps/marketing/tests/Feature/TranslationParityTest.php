<?php

use Illuminate\Support\Arr;

/**
 * English and Swahili are both mandatory: every key must exist in every
 * locale, be non-empty and carry the same :placeholders.
 *
 * @return array<string, string>
 */
function translationsFor(string $locale): array
{
    $strings = [];

    foreach (glob(lang_path($locale).'/*.php') ?: [] as $file) {
        $group = basename($file, '.php');

        foreach (Arr::dot(require $file) as $key => $value) {
            $strings["{$group}.{$key}"] = $value;
        }
    }

    return $strings;
}

/** @return list<string> */
function placeholdersIn(string $value): array
{
    preg_match_all('/:([a-z_]+)/i', $value, $matches);
    $names = array_map('strtolower', $matches[1]);
    sort($names);

    return $names;
}

// Datasets resolve before the app boots, so read the locale list directly.
$others = array_values(array_diff(array_keys((require __DIR__.'/../../config/marketing.php')['locales']), ['en']));

it('has the same keys as English', function (string $locale) {
    $english = translationsFor('en');
    $other = translationsFor($locale);

    expect(array_keys(array_diff_key($english, $other)))->toBe([], "Missing in {$locale}")
        ->and(array_keys(array_diff_key($other, $english)))->toBe([], "Only in {$locale}");
})->with($others);

it('has no empty strings', function (string $locale) {
    $empty = array_keys(array_filter(translationsFor($locale), fn ($value) => ! is_string($value) || trim($value) === ''));

    expect($empty)->toBe([]);
})->with(['en', ...$others]);

it('keeps the same placeholders as English', function (string $locale) {
    $other = translationsFor($locale);
    $mismatched = [];

    foreach (translationsFor('en') as $key => $value) {
        if (isset($other[$key]) && placeholdersIn($value) !== placeholdersIn($other[$key])) {
            $mismatched[] = $key;
        }
    }

    expect($mismatched)->toBe([]);
})->with($others);
