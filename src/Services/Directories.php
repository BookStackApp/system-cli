<?php

namespace Cli\Services;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Directories
{

    public static function move(string $src, string $target): void
    {
        static::copy($src, $target);
        static::delete($src);
    }

    public static function copy(string $src, string $target): void
    {
        mkdir($target);

        /** @var RecursiveDirectoryIterator $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            $srcPath = $fileinfo->getRealPath();
            $subPath = $files->getSubPathName();
            $destPath = Paths::join($target, $subPath);
            if ($fileinfo->isDir()) {
                $result = mkdir($destPath);
            } else {
                $result = copy($srcPath, $destPath);
            }

            if ($result === false) {
                throw new \Exception("Failed to copy file or directory from {$srcPath} to {$destPath}");
            }
        }
    }

    public static function delete(string $dir): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $path = $fileinfo->getRealPath();
            if ($fileinfo->isDir()) {
                $result = rmdir($path);
            } else {
                $result = unlink($path);
            }

            if ($result === false) {
                throw new \Exception("Failed to delete file or directory at {$path}");
            }
        }

        rmdir($dir);
    }
}