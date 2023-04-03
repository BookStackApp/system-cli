<?php declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class InitCommandTest extends TestCase
{
    public function test_command_creates_an_instance_in_cwd()
    {
        $dir = $this->getEmptyTestDir();
        chdir($dir);

        $result = $this->runCommand('init');
        $result->assertSuccessfulExit();

        $this->assertFileExists($dir . '/vendor/autoload.php');
        $this->assertFileExists($dir . '/.env');

        $envData = file_get_contents($dir . '/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{30,60}$/m', $envData);

        $result->assertStdoutContains("A BookStack install has been initialized at: " . $dir);

        $this->deleteDirectory($dir);
    }

    public function test_command_custom_path_validation()
    {
        $dir = $this->getEmptyTestDir();
        $file = $dir . '/out.txt';
        file_put_contents($file, 'hello');

        $result = $this->runCommand('init', ['target-directory' => $file]);
        $result->assertErrorExit();
        $result->assertStderrContains("Was provided [{$file}] as an install path but existing file provided.");

        $result = $this->runCommand('init', ['target-directory' => $dir]);
        $result->assertErrorExit();
        $result->assertStderrContains("Expected install directory to be empty but existing files found in [{$dir}] target location.");

        $result = $this->runCommand('init', ['target-directory' => '/my/duck/says/hello']);
        $result->assertErrorExit();
        $result->assertStderrContains("Could not resolve provided [/my/duck/says/hello] path to an existing folder.");

        $this->deleteDirectory($dir);
    }
}