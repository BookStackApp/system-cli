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
        $this->assertCount(0, glob('storage/backups/bookstack-backup-*.zip'));

        file_put_contents('/var/www/bookstack/themes/test.txt', static::$uniqueUserEmail . '-themes');
        file_put_contents('/var/www/bookstack/public/uploads/test.txt', static::$uniqueUserEmail . '-public-uploads');
        file_put_contents('/var/www/bookstack/storage/uploads/test.txt', static::$uniqueUserEmail . '-storage-uploads');

        $result = $this->runCommand('backup');
        $result->assertSuccessfulExit();
        $result->assertStdoutContains("Backup finished.");

        $this->assertCount(1, glob('storage/backups/bookstack-backup-*.zip'));
        $zipFile = glob('storage/backups/bookstack-backup-*.zip')[0];

        $zip = new \ZipArchive();
        $zip->open($zipFile);
        $dbDump = $zip->getFromName('db.sql');
        $this->assertStringContainsString('APP_KEY=', $zip->getFromName('.env'));
        $this->assertStringContainsString(static::$uniqueUserEmail, $dbDump);
        $this->assertStringContainsString('page_revisions', $dbDump);
        $this->assertStringContainsString(static::$uniqueUserEmail . '-public-uploads', $zip->getFromName('public/uploads/test.txt'));
        $this->assertStringContainsString(static::$uniqueUserEmail . '-storage-uploads', $zip->getFromName('storage/uploads/test.txt'));
        $this->assertStringContainsString(static::$uniqueUserEmail . '-themes', $zip->getFromName('themes/test.txt'));

        unlink($zipFile);
    }

    public function test_no_options()
    {
        chdir('/var/www/bookstack');
        $this->assertCount(0, glob('bookstack-backup-*.zip'));

        $result = $this->runCommand('backup', [
            '--no-database' => true,
            '--no-uploads' => true,
            '--no-themes' => true,
        ]);
        $result->assertSuccessfulExit();

        $zipFile = glob('storage/backups/bookstack-backup-*.zip')[0];

        $zip = new \ZipArchive();
        $zip->open($zipFile);
        $this->assertLessThan(3, $zip->numFiles);
        $this->assertFalse($zip->getFromName('db.sql'));
        $this->assertFalse($zip->getFromName('themes/.gitignore'));
        $this->assertFalse($zip->getFromName('public/uploads/.gitignore'));
        $this->assertFalse($zip->getFromName('storage/uploads/.gitignore'));

        unlink($zipFile);
    }

    public function test_app_directory_option()
    {
        chdir('/var');
        $this->assertCount(0, glob('bookstack-backup-*.zip'));

        $result = $this->runCommand('backup', [
            '--no-database' => true,
            '--no-uploads' => true,
            '--no-themes' => true,
            '--app-directory' => '/var/www/bookstack',
            'backup-path' => './'
        ]);
        $result->assertSuccessfulExit();

        $zipFile = glob('bookstack-backup-*.zip')[0] ?? null;
        $this->assertNotNull($zipFile);

        unlink($zipFile);
    }

    public function test_backup_path_argument()
    {
        chdir('/var/www/bookstack');
        $this->assertCount(0, glob('/home/bookstack-backup-*.zip'));
        $this->assertFileDoesNotExist('/home/my-cool-backup.zip');

        $result = $this->runCommand('backup', [
            'backup-path' => '/home/my-cool-backup.zip',
            '--no-database' => true,
            '--no-uploads' => true,
            '--no-themes' => true,
        ]);
        $result->assertSuccessfulExit();
        $this->assertFileExists('/home/my-cool-backup.zip');
        unlink('/home/my-cool-backup.zip');

        $result = $this->runCommand('backup', [
            'backup-path' => '/home/',
            '--no-database' => true,
            '--no-uploads' => true,
            '--no-themes' => true,
        ]);
        $result->assertSuccessfulExit();
        $this->assertCount(1, glob('/home/bookstack-backup-*.zip'));
        $zipFile = glob('/home/bookstack-backup-*.zip')[0];
        unlink($zipFile);
    }

    public function test_command_resolves_nested_symlinks()
    {
        $symDirs = ['storage/uploads/files', 'storage/uploads/images'];
        exec('cp -r /var/www/bookstack /var/www/bookstack-symlink-backup');
        mkdir('/symlinks');
        foreach ($symDirs as $dir) {
            $targetFile = str_replace('/', '-', $dir);
            $code = 0;
            $output = null;
            exec("mkdir -p /var/www/bookstack-symlink-backup/{$dir}", $output, $code);
            exec("mv /var/www/bookstack-symlink-backup/{$dir} /symlinks/{$targetFile}", $output, $code);
            exec("ln -s /symlinks/{$targetFile} /var/www/bookstack-symlink-backup/{$dir}", $output, $code);
            file_put_contents("/symlinks/{$targetFile}/test.txt", "Hello from $dir");
            if ($code !== 0) {
                $this->fail("Error when setting up symlinks");
            }
        }

        chdir('/var/www/bookstack-symlink-backup');
        $this->assertCount(0, glob('storage/backups/bookstack-backup-*.zip'));
        $result = $this->runCommand('backup');
        $result->assertSuccessfulExit();
        $this->assertCount(1, glob('storage/backups/bookstack-backup-*.zip'));
        $zipFile = glob('storage/backups/bookstack-backup-*.zip')[0];

        $zip = new \ZipArchive();
        $zip->open($zipFile);
        foreach ($symDirs as $dir) {
            $fileData = $zip->getFromName("{$dir}/test.txt");
            $this->assertNotFalse($fileData);
            $this->assertStringContainsString("Hello from {$dir}", $fileData);
        }
        $zip->close();

        exec('rm -rf /symlinks');
        exec('rm -rf /var/www/bookstack-symlink-backup');
    }

}