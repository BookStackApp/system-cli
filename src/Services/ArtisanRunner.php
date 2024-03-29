<?php declare(strict_types=1);

namespace Cli\Services;

use Exception;

class ArtisanRunner
{
    public function __construct(
        protected string $appDir
    ) {
    }

    public function run(array $commandArgs)
    {
        $errors = (new ProgramRunner('php', '/usr/bin/php'))
            ->withTimeout(600)
            ->withIdleTimeout(600)
            ->withEnvironment(EnvironmentLoader::load($this->appDir))
            ->runCapturingAllOutput([
                $this->appDir . DIRECTORY_SEPARATOR . 'artisan',
                '-n', '-q',
                ...$commandArgs
            ]);

        if ($errors) {
            $cmdString = implode(' ', $commandArgs);
            throw new Exception("Failed 'php artisan {$cmdString}' with errors:\n" . $errors);
        }
    }
}