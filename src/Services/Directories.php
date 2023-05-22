<?php declare(strict_types=1);

namespace Cli\Services;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Directories
{

    public static function move(string $src, string $target): void
    {
        static::copy($src, $target);
        static::delete($src);
    }

    public static function copy(string $src, string $target): void
    {
        if (!file_exists($target)) {
            mkdir($target);
        }

        /** @var RecursiveDirectoryIterator $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            $srcPath = $fileinfo->getRealPath();
            $subPath = $files->getSubPathName();
            $destPath = Paths::join($target, $subPath);
            $result = true;
            if ($fileinfo->isDir() && !file_exists($destPath)) {
                echo $destPath . "\n";
                $result = mkdir($destPath);
            } else if ($fileinfo->isFile()) {
                $result = copy($srcPath, $destPath);
            }

            if ($result === false) {
                throw new \Exception("Failed to copy file or directory from {$srcPath} to {$destPath}");
            }
        }
    }

    /**
     * Delete the contents of the given directory.
     * Will not delete symlinked folders to ensure that symlinks remain consistent,
     * but will aim to delete contents of symlinked folders.
     */
    public static function delete(string $dir): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $links = '::';

        /** @var SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            $path = $fileinfo->getRealPath();
            $result = true;
            if ($fileinfo->isDir()) {
                if ($fileinfo->isLink()) {
                    $links .= $fileinfo->getPath() . '::';
                } else if (!str_contains($links, '::' . $path)) {
                    $result = rmdir($path);
                }
            } else if ($fileinfo->isFile()) {
                $result = unlink($path);
            }

            if ($result === false) {
                throw new \Exception("Failed to delete file or directory at {$path}");
            }
        }

        if ($links === '::') {
            rmdir($dir);
        }
    }
}