<?php

namespace Tests;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function getTestDataDirectory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'test-data';
    }

    protected function getEmptyTestDir(): string
    {
        $dir = $this->getTestDataDirectory() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(10));
        mkdir($dir);
        return $dir;
    }

    protected function deleteDirectory(string $dir): void
    {
        // For safety, only delete directories within our test data dir.
        if (!str_starts_with($dir, $this->getTestDataDirectory())) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }

    protected function getApp(): Application
    {
        return require dirname(__DIR__) . '/src/app.php';
    }

    protected function runCommand(string $command, array $args = []): CommandResult
    {
        $app = $this->getApp();
        $command = $app->find($command);

        $err = null;
        $commandTester = new CommandTester($command);

        try {
            $commandTester->execute($args);
        } catch (\Exception $exception) {
            $err = $exception;
        }

        return new CommandResult($commandTester, $err);
    }
}