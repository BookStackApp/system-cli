<?php declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class InitCommandTest extends TestCase
{
    public function test_command_creates_an_instance_in_cwd()
    {
        $dir = $this->getEmptyTestDir();
        chdir($dir);

        $commandTester = $this->runCommand('init');
        $commandTester->assertCommandIsSuccessful();

        $this->assertFileExists($dir . '/vendor/autoload.php');
        $this->assertFileExists($dir . '/.env');

        $envData = file_get_contents($dir . '/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{30,60}$/m', $envData);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("A BookStack install has been initialized at: " . $dir, $output);

        $this->deleteDirectory($dir);
    }
}