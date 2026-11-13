<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    // Risky rules are allowed only for declare_strict_types, which the project requires in every file.
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in([__DIR__.'/src', __DIR__.'/tests'])
    )
;
