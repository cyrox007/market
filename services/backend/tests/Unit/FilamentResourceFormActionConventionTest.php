<?php

namespace Tests\Unit;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FilamentResourceFormActionConventionTest extends TestCase
{
    #[DataProvider('resourceFormPageProvider')]
    public function test_resource_form_page_uses_shared_save_action_convention(
        string $class,
        string $expectedBaseClass,
    ): void {
        $this->assertTrue(
            is_subclass_of($class, $expectedBaseClass),
            "{$class} must extend {$expectedBaseClass}.",
        );
    }

    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function resourceFormPageProvider(): iterable
    {
        $root = dirname(__DIR__, 2).'/app/Filament/Resources';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $name = $file->getBasename('.php');
            $expectedBaseClass = match (true) {
                str_starts_with($name, 'Create') => CreateRecord::class,
                str_starts_with($name, 'Edit') => EditRecord::class,
                default => null,
            };

            if ($expectedBaseClass === null) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
                continue;
            }

            $class = $namespaceMatch[1].'\\'.$name;

            if ($class === $expectedBaseClass) {
                continue;
            }

            yield $class => [$class, $expectedBaseClass];
        }
    }
}
