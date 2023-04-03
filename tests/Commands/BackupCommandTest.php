<?php declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    static string $uniqueUserEmail = '';

    public static function setUpBeforeClass(): void
    {
        static::$uniqueUserEmail = bin2hex(random_bytes(10)) . '@example.com';
        chdir('/var/www/bookstack');
        // Ensure the database is created and create an admin user we can look out for in the data.
        exec('php artisan migrate --force');
        exec('php artisan bookstack:create-admin --email="' . static::$uniqueUserEmail . '" --name="Bazza" --password="hunter200"');
    }
    public function test_command_does_full_backup_of_cwd_instance_by_default()
    {
        chdir('/var/www/bookstack');

        $this->assertCount(0, glob('bookstack-backup-*.zip'));

        file_put_contents('/var/www/bookstack/themes/test.txt', static::$uniqueUserEmail . '-themes');
        file_put_contents('/var/www/bookstack/public/uploads/test.txt', static::$uniqueUserEmail . '-public-uploads');
        file_put_contents('/var/www/bookstack/storage/uploads/test.txt', static::$uniqueUserEmail . '-storage-uploads');

        $result = $this->runCommand('backup');
        $result->dumpError();
        $result->assertSuccessfulExit();
        $result->assertStdoutContains("Backup finished.");

        $this->assertCount(1, glob('bookstack-backup-*.zip'));
        $zipFile = glob('bookstack-backup-*.zip')[0];

        $zip = new \ZipArchive();
        $zip->open($zipFile);
        $dbDump = $zip->getFromName('db.sql');
        $this->assertStringContainsString('APP_KEY=', $zip->getFromName('.env'));
        $this->assertStringContainsString(static::$uniqueUserEmail, $dbDump);
        $this->assertStringContainsString('page_revisions', $dbDump);
        $this->assertStringContainsString(static::$uniqueUserEmail . '-public-uploads', $zip->getFromName('public/uploads/test.txt'));
        $this->assertStringContainsString(static::$uniqueUserEmail . '-storage-uploads', $zip->getFromName('storage/uploads/test.txt'));
        $this->assertStringContainsString(static::$uniqueUserEmail . '-themes', $zip->getFromName('themes/test.txt'));
    }
}