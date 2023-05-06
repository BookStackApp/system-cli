<?php declare(strict_types=1);

namespace Cli\Services;

class Paths
{

    /**
     * Get the full real path, resolving symbolic links, to
     * the existing file/directory of the given path.
     * @throws \Exception
     */
    public static function real(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw new \Exception("Path {$path} could not be resolved to a location on the filesystem");
        }

        return $real;
    }

    /**
     * Join together the given path components.
     * Does no resolving or cleaning.
     * Only the $base will remain absolute if so,
     * $parts are assumed to treated as non-absolute paths.
     */
    public static function join(string $base, string ...$parts): string
    {
        $outParts = [rtrim($base, '/\\')];
        foreach ($parts as $part) {
            $outParts[] = trim($part, '/\\');
        }

        return implode(DIRECTORY_SEPARATOR, $outParts);
    }

    /**
     * Resolve the full path for the given path/sub-path.
     * If the provided path is not absolute, it will be returned
     * be resolved to the provided $base or current working directory if
     * no $base is provided.
     */
    public static function resolve(string $path, string $base = ''): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return DIRECTORY_SEPARATOR . self::clean($path);
        }

        $base = rtrim($base ?: getcwd(), '/');
        $joined = $base . '/' . $path;
        $absoluteBase = (str_starts_with($base, '/') || str_starts_with($base, '\\'));
        return ($absoluteBase ? '/' : '') . self::clean($joined);
    }

    /**
     * Clean the given path so that all up/down navigations are resolved,
     * and so its using system-correct directory separators.
     * Credit to Sven Arduwie in PHP docs:
     * https://www.php.net/manual/en/function.realpath.php#84012
     */
    private static function clean(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}