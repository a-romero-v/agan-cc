<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
 * Context boundaries. The layer direction inside a context is checked by deptrac.yaml.
 *
 * Rules (see CLAUDE.md, "Architecture"):
 *   - Everything may use its own context and Shared.
 *   - Application event handlers may also use the domain events of other contexts.
 *   - Infrastructure may also use the commands, queries, read models and domain
 *     exceptions of other contexts (cross-context adapters behind a port of the
 *     calling context).
 *   - Nothing else crosses a context boundary.
 *   - Shared uses no context. Only the dev fixtures may reach into every context.
 *
 * Written in PHP instead of YAML because the rules are the same for every context.
 */
return static function (DeptracConfig $config): void {
    $contexts = ['Cart', 'Product', 'Inventory', 'User'];

    // Disjoint parts of a context; "Core" is everything that matches none of them.
    // Namespace separators are written as a single backslash: Deptrac escapes them for the regex itself.
    $parts = [
        'Event' => 'Domain\\Event\\',
        'Exception' => 'Domain\\Exception\\',
        'Api' => 'Application\\(Command|Query)\\',
        'EventHandler' => 'Application\\EventHandler\\',
        'Infrastructure' => 'Infrastructure\\',
    ];

    $fixturesPattern = '^App\\Shared\\Infrastructure\\DataFixtures\\';
    $shared = Layer::withName('Shared')->collectors(
        BoolConfig::create()
            ->must(ClassLikeConfig::create('^App\\(Shared\\|Kernel$)'))
            ->mustNot(ClassLikeConfig::create($fixturesPattern)),
    );
    $fixtures = Layer::withName('SharedFixtures')->collectors(ClassLikeConfig::create($fixturesPattern));

    /** @var array<string, array<string, Layer>> $layers */
    $layers = [];
    foreach ($contexts as $context) {
        $prefix = '^App\\'.$context.'\\';
        $core = BoolConfig::create()->must(ClassLikeConfig::create($prefix));
        foreach ($parts as $part => $pattern) {
            $layers[$context][$part] = Layer::withName($context.$part)
                ->collectors(ClassLikeConfig::create($prefix.$pattern));
            $core->mustNot(ClassLikeConfig::create($prefix.$pattern));
        }
        $layers[$context]['Core'] = Layer::withName($context.'Core')->collectors($core);
    }

    $rulesets = [Ruleset::forLayer($fixtures)->accesses($shared)];
    foreach ($layers as $context => $own) {
        $rulesets[0]->accesses(...array_values($own));

        foreach ($own as $part => $layer) {
            $allowed = [$shared, ...array_values(array_filter($own, static fn (Layer $l): bool => $l !== $layer))];

            foreach ($layers as $otherContext => $other) {
                if ($otherContext === $context) {
                    continue;
                }
                if ('EventHandler' === $part) {
                    $allowed[] = $other['Event'];
                }
                if ('Infrastructure' === $part) {
                    $allowed[] = $other['Api'];
                    $allowed[] = $other['Exception'];
                }
            }

            $rulesets[] = Ruleset::forLayer($layer)->accesses(...$allowed);
        }
    }

    $config
        ->paths('./src')
        ->layers($shared, $fixtures, ...array_merge(...array_map(array_values(...), array_values($layers))))
        ->rulesets(...$rulesets);
};
