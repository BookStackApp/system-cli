<?php declare(strict_types=1);

namespace Cli\Services;

use Exception;

class RequirementsValidator
{
    protected static string $phpVersion = '8.0.2';
    protected static array $extensions = [
        'curl',
        'dom',
        'fileinfo',
        'gd',
        'iconv',
        'libxml',
        'mbstring',
        'mysqlnd',
        'pdo_mysql',
        'session',
        'simplexml',
        'tokenizer',
        'xml',
    ];

    /**
     * Ensure the required PHP extensions are installed for this command.
     * @throws Exception
     */
    public static function validate(): void
    {
        $errors = [];

        if (version_compare(PHP_VERSION, static::$phpVersion) < 0) {
            $errors[] = sprintf("PHP >= %s is required to install BookStack.", static::$phpVersion);
        }

        foreach (static::$extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = "The \"{$extension}\" PHP extension is required but not active.";
            }
        }

        try {
            (new ProgramRunner('git', '/usr/bin/git'))->ensureFound();
            (new ProgramRunner('php', '/usr/bin/php'))->ensureFound();
        } catch (Exception $exception) {
            $errors[] = $exception->getMessage();
        }

        if (count($errors) > 0) {
            throw new Exception("Requirements failed with following errors:\n" . implode("\n", $errors));
        }
    }
}