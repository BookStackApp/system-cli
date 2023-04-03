<?php declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class UpdateCommandTest extends TestCase
{
    public function test_command_updates_instance_in_cwd()
    {
        chdir('/var/www/bookstack');

        $result = $this->runCommand('update');
        $result->assertSuccessfulExit();
        $result->assertStdoutContains("Your BookStack instance at [/var/www/bookstack] has been updated!");
    }

    public function test_composer_gets_downloaded_locally_if_not_found()
    {
        chdir('/var/www/bookstack');

        rename('/usr/local/bin/composer', '/usr/local/bin/hiddencomposer');

        $this->assertFileDoesNotExist('/var/www/bookstack/composer');

        $result = $this->runCommand('update');
        $result->assertSuccessfulExit();
        $result->assertStdoutContains("Composer does not exist, downloading a local copy...");
        $result->assertStdoutContains("Your BookStack instance at [/var/www/bookstack] has been updated!");

        $this->assertFileExists('/var/www/bookstack/composer');
        unlink('/var/www/bookstack/composer');

        rename('/usr/local/bin/hiddencomposer', '/usr/local/bin/composer');
    }

    public function test_command_rejects_on_no_instance_found()
    {
        chdir('/home');

        $result = $this->runCommand('update');
        $result->assertErrorExit();
        $result->assertStderrContains('Could not find a valid BookStack installation');
    }

    public function test_command_can_be_provided_app_directory_option()
    {
        chdir('/home');

        $result = $this->runCommand('update', ['--app-directory' => '/var/www/bookstack']);
        $result->assertSuccessfulExit();
        $result->assertStdoutContains("Your BookStack instance at [/var/www/bookstack] has been updated!");
    }
}