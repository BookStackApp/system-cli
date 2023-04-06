<?php declare(strict_types=1);

namespace Tests\Commands;

use mysqli;
use Tests\TestCase;

class RestoreCommandTest extends TestCase
{

    public function test_restore_into_cwd_by_default_with_all_content_types()
    {
        $mysql = new mysqli('db', 'bookstack', 'bookstack', 'bookstack');
        $mysql->query('CREATE TABLE xx_testing (labels varchar(255));');

        $result = $mysql->query('SHOW TABLES LIKE \'zz_testing\';');
        $this->assertEquals(0, mysqli_num_rows($result));

        $zipFile = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('.env', "APP_KEY=abc123\nAPP_URL=https://example.com");
            $zip->addFromString('public/uploads/test.txt', 'hello-public-uploads');
            $zip->addFromString('storage/uploads/test.txt', 'hello-storage-uploads');
            $zip->addFromString('themes/test.txt', 'hello-themes');
            $zip->addFromString('db.sql', "CREATE TABLE zz_testing (names varchar(255));\nINSERT INTO zz_testing values ('barry');");
        });

        exec('cp -r /var/www/bookstack /var/www/bookstack-restore');
        chdir('/var/www/bookstack-restore');

        $result = $this->runCommand('restore', [
            'backup-zip' => $zipFile,
        ], [
            'yes', '1'
        ]);

        $result->dumpError();
        $result->assertSuccessfulExit();
        $result->assertStdoutContains('✔ .env Config File');
        $result->assertStdoutContains('✔ Themes Folder');
        $result->assertStdoutContains('✔ Public File Uploads');
        $result->assertStdoutContains('✔ Private File Uploads');
        $result->assertStdoutContains('✔ Database Dump');
        $result->assertStdoutContains('Restore operation complete!');

        $result = $mysql->query('SELECT * FROM zz_testing where names = \'barry\';');
        $this->assertEquals(1, mysqli_num_rows($result));
        $result = $mysql->query('SHOW TABLES LIKE \'xx_testing\';');
        $this->assertEquals(0, mysqli_num_rows($result));

        $this->assertStringEqualsFile('/var/www/bookstack-restore/public/uploads/test.txt', 'hello-public-uploads');
        $this->assertStringEqualsFile('/var/www/bookstack-restore/storage/uploads/test.txt', 'hello-storage-uploads');
        $this->assertStringEqualsFile('/var/www/bookstack-restore/themes/test.txt', 'hello-themes');

        $mysql->query("DROP TABLE zz_testing;");
        exec('rm -rf /var/www/bookstack-restore');
    }

    public function test_command_fails_on_zip_with_no_expected_contents()
    {
        $zipFile = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('spaghetti', "Hello world!");
        });

        chdir('/var/www/bookstack');

        $result = $this->runCommand('restore', [
            'backup-zip' => $zipFile,
        ]);

        $result->assertErrorExit();
        $result->assertStderrContains("Provided ZIP backup [{$zipFile}] does not have any expected restorable content.");
    }

    public function test_limited_restore_using_app_directory_option()
    {
        $zipFile = $this->buildZip(function (\ZipArchive $zip) {
            $zip->addFromString('db.sql', "CREATE TABLE zz_testing (names varchar(255));");
            $zip->addFromString('themes/hello.txt', "limited restore test!");
        });

        chdir('/home');

        $result = $this->runCommand('restore', [
            'backup-zip' => $zipFile,
            '--app-directory' => '/var/www/bookstack'
        ], ['yes']);

        $result->assertSuccessfulExit();
        $result->assertStdoutContains('❌ .env Config File');
        $result->assertStdoutContains('✔ Themes Folder');
        $result->assertStdoutContains('❌ Public File Uploads');
        $result->assertStdoutContains('❌ Private File Uploads');
        $result->assertStdoutContains('✔ Database Dump');
        $this->assertStringEqualsFile('/var/www/bookstack/themes/hello.txt', 'limited restore test!');

        unlink('/var/www/bookstack/themes/hello.txt');
        $mysql = new mysqli('db', 'bookstack', 'bookstack', 'bookstack');
        $mysql->query("DROP TABLE zz_testing;");
    }

    protected function buildZip(callable $builder): string
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'cli-test');
        $testZip = new \ZipArchive('');
        $testZip->open($zipFile);
        $builder($testZip);
        $testZip->close();

        return $zipFile;
    }
}