<?php

declare(strict_types=1);

namespace App\Tests\Shared;

/**
 * A throwaway directory for tests that need real files. Call remove() in tearDown().
 */
final class TemporaryDirectory
{
    public readonly string $path;

    public function __construct(string $prefix)
    {
        $this->path = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(6));
        mkdir($this->path);
    }

    /**
     * Writes a file below the directory, creating missing folders, and returns its full path.
     */
    public function write(string $relativePath, string $content): string
    {
        $file = $this->path.'/'.$relativePath;

        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), recursive: true);
        }

        file_put_contents($file, $content);

        return $file;
    }

    public function remove(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->path);
    }
}
