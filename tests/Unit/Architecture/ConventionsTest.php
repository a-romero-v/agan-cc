<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use App\Kernel;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Exception\AbstractDomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Enforces the structure and naming conventions of CLAUDE.md that can be
 * decided mechanically. Deptrac checks who may depend on whom; this test
 * checks names and locations.
 *
 * A new convention gets a check here, not just a sentence in the docs.
 */
final class ConventionsTest extends TestCase
{
    /** Classes that may be extended, each for a reason. */
    private const array NOT_FINAL = [
        AbstractDomainException::class, // base class of all domain exceptions
        Kernel::class, // Symfony's kernel, excluded from the conventions
    ];

    public function testEveryClassIsFinal(): void
    {
        $this->assertNoViolations(function (\ReflectionClass $class): ?string {
            if ($class->isInterface() || $class->isTrait() || $class->isEnum() || \in_array($class->getName(), self::NOT_FINAL, true)) {
                return null;
            }

            return $class->isFinal() ? null : 'must be final (or be listed in NOT_FINAL with a reason)';
        });
    }

    public function testACommandFolderHoldsOnlyItsCommandAndItsHandler(): void
    {
        $this->assertUseCaseFolders('Command');
    }

    public function testAQueryFolderHoldsOnlyItsQueryAndItsHandler(): void
    {
        $this->assertUseCaseFolders('Query');
    }

    public function testReadModelsAreFinalReadonlyViews(): void
    {
        $this->assertNoViolations(function (\ReflectionClass $class, string $path): ?string {
            $inReadModel = str_contains($path, '/Application/Query/ReadModel/');
            $isView = str_ends_with($class->getShortName(), 'View');

            return match (true) {
                $inReadModel && !$isView => 'is in Application/Query/ReadModel/, so its name must end with "View"',
                $inReadModel && !($class->isFinal() && $class->isReadOnly()) => 'is a read model and must be final readonly',
                !$inReadModel && $isView => 'is named *View and must live in Application/Query/ReadModel/',
                default => null,
            };
        });
    }

    public function testEventHandlersEndWithHandler(): void
    {
        $this->assertNoViolations(static fn (\ReflectionClass $class, string $path): ?string => str_contains($path, '/Application/EventHandler/') && !str_ends_with($class->getShortName(), 'Handler')
            ? 'is in Application/EventHandler/, so its name must end with "Handler"'
            : null);
    }

    public function testDomainPortsAreInterfaces(): void
    {
        $this->assertNoViolations(static fn (\ReflectionClass $class, string $path): ?string => str_contains($path, '/Domain/Port/') && !$class->isInterface()
            ? 'is in Domain/Port/, which holds only interfaces'
            : null);
    }

    public function testDomainEventsAreFinalReadonlyAndMarked(): void
    {
        $this->assertNoViolations(static function (\ReflectionClass $class, string $path): ?string {
            if (!str_contains($path, '/Domain/Event/')) {
                return null;
            }

            return $class->isFinal() && $class->isReadOnly() && $class->implementsInterface(DomainEvent::class)
                ? null
                : 'is in Domain/Event/ and must be a final readonly class implementing DomainEvent';
        });
    }

    public function testRecordsLiveInPersistence(): void
    {
        $this->assertNoViolations(static fn (\ReflectionClass $class, string $path): ?string => str_ends_with($class->getShortName(), 'Record') && !str_contains($path, '/Infrastructure/Persistence/')
            ? 'is named *Record and must live in Infrastructure/Persistence/'
            : null);
    }

    public function testControllersLiveInHttp(): void
    {
        $this->assertNoViolations(static fn (\ReflectionClass $class, string $path): ?string => str_ends_with($class->getShortName(), 'Controller') && !str_contains($path, '/Infrastructure/Http/')
            ? 'is named *Controller and must live in Infrastructure/Http/'
            : null);
    }

    /**
     * Application/<Command|Query>/<Name>/ holds exactly <Name><Command|Query> and <Name>Handler.
     * Application/Query/ReadModel/ is the one folder that is not a use case.
     */
    private function assertUseCaseFolders(string $kind): void
    {
        $this->assertNoViolations(static function (\ReflectionClass $class, string $path) use ($kind): ?string {
            $marker = '/Application/'.$kind.'/';
            if (!str_contains($path, $marker)) {
                return null;
            }

            $inside = substr($path, strpos($path, $marker) + \strlen($marker), -\strlen('.php'));
            if (str_starts_with($inside, 'ReadModel/')) {
                return null;
            }

            $parts = explode('/', $inside);
            if (2 !== \count($parts)) {
                return \sprintf('must be in a folder of its own use case: Application/%s/<Name>/', $kind);
            }

            [$useCase, $name] = $parts;

            return \in_array($name, [$useCase.$kind, $useCase.'Handler'], true)
                ? null
                : \sprintf('is in the use case folder "%1$s" and must be named %1$s%2$s or %1$sHandler', $useCase, $kind);
        });
    }

    /**
     * @param callable(\ReflectionClass<object>, string): ?string $rule returns what is wrong with the class, or null
     */
    private function assertNoViolations(callable $rule): void
    {
        $violations = [];
        foreach (self::classes() as $path => $class) {
            if (null !== $problem = $rule($class, $path)) {
                $violations[] = \sprintf('%s %s.', $class->getName(), $problem);
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Every class, interface, trait and enum in src/, keyed by its path below src/ (with a leading slash).
     *
     * @return array<string, \ReflectionClass<object>>
     */
    private static function classes(): array
    {
        $classes = [];
        foreach (new Finder()->files()->in(\dirname(__DIR__, 3).'/src')->name('*.php')->sortByName() as $file) {
            $relative = $file->getRelativePathname();
            /** @var class-string $name */
            $name = 'App\\'.str_replace('/', '\\', substr($relative, 0, -\strlen('.php')));
            $classes['/'.$relative] = new \ReflectionClass($name);
        }

        self::assertNotEmpty($classes);

        return $classes;
    }
}
