<?php declare(strict_types=1);

namespace Cli\Commands;

use Cli\Services\AppLocator;
use Cli\Services\ArtisanRunner;
use Cli\Services\BackupZip;
use Cli\Services\Directories;
use Cli\Services\EnvironmentLoader;
use Cli\Services\InteractiveConsole;
use Cli\Services\MySqlRunner;
use Cli\Services\Paths;
use Cli\Services\ProgramRunner;
use Cli\Services\RequirementsValidator;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('restore');
        $this->addArgument('backup-zip', InputArgument::REQUIRED, 'Path to the ZIP file containing your backup.');
        $this->setDescription('Restore data and files from a backup ZIP file.');
        $this->addOption('app-directory', null, InputOption::VALUE_OPTIONAL, 'BookStack install directory to restore into', '');
    }

    /**
     * @throws CommandError
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interactions = new InteractiveConsole($this->getHelper('question'), $input, $output);

        $output->writeln("<warn>Warning!</warn>");
        $output->writeln("<warn>- A restore operation will overwrite and remove files & content from an existing instance.</warn>");
        $output->writeln("<warn>- Any existing tables within the configured database will be dropped.</warn>");
        $output->writeln("<warn>- You should only restore into an instance of the same or newer BookStack version.</warn>");
        $output->writeln("<warn>- This command won't handle, restore or address any server configuration.</warn>");

        $appDir = AppLocator::require($input->getOption('app-directory'));
        $output->writeln("<info>Checking system requirements...</info>");
        RequirementsValidator::validate();
        (new ProgramRunner('mysql', '/usr/bin/mysql'))->ensureFound();

        $zipPath = realpath($input->getArgument('backup-zip'));
        $zip = new BackupZip($zipPath);
        $contents = $zip->getContentsOverview();

        $output->writeln("\n<info>Contents found in the backup ZIP:</info>");
        $hasContent = false;
        foreach ($contents as $info) {
            $output->writeln(($info['exists'] ? '✔ ' : '❌ ') . $info['desc']);
            if ($info['exists']) {
                $hasContent = true;
            }
        }

        if (!$hasContent) {
            throw new CommandError("Provided ZIP backup [{$zipPath}] does not have any expected restorable content.");
        }

        $output->writeln("<info>The checked elements will be restored into [{$appDir}].</info>");
        $output->writeln("<warn>Existing content will be overwritten.</warn>");

        if (!$interactions->confirm("Do you want to continue?")) {
            $output->writeln("<info>Stopping restore operation.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<info>Extracting ZIP into temporary directory...</info>");
        $extractDir = Paths::join($appDir, 'restore-temp-' . time());
        if (!mkdir($extractDir)) {
            throw new CommandError("Could not create temporary extraction directory at [{$extractDir}].");
        }
        $zip->extractInto($extractDir);

        $envChanges = [];
        if ($contents['env']['exists']) {
            $output->writeln("<info>Restoring and merging .env file...</info>");
            $envChanges = $this->restoreEnv($extractDir, $appDir, $output, $interactions);
        }

        $folderLocations = ['themes', 'public/uploads', 'storage/uploads'];
        foreach ($folderLocations as $folderSubPath) {
            if ($contents[$folderSubPath]['exists']) {
                $output->writeln("<info>Restoring {$folderSubPath} folder...</info>");
                $this->restoreFolder($folderSubPath, $appDir, $extractDir);
            }
        }

        $artisan = (new ArtisanRunner($appDir));
        if ($contents['db']['exists']) {
            $output->writeln("<info>Restoring database from SQL dump...</info>");
            $this->restoreDatabase($appDir, $extractDir);

            $output->writeln("<info>Running database migrations...</info>");
            $artisan->run(['migrate', '--force']);
        }

        if ($envChanges && $envChanges['old_url'] !== $envChanges['new_url']) {
            $output->writeln("<info>App URL change made, updating database with URL change...</info>");
            $artisan->run([
                'bookstack:update-url', '--force',
                $envChanges['old_url'], $envChanges['new_url'],
            ]);
        }

        $output->writeln("<info>Clearing app caches...</info>");
        $artisan->run(['cache:clear']);
        $artisan->run(['config:clear']);
        $artisan->run(['view:clear']);

        $output->writeln("<info>Cleaning up extract directory...</info>");
        Directories::delete($extractDir);

        $output->writeln("<success>\nRestore operation complete!</success>");
        $output->writeln("<info>You may need to fix file/folder permissions so that the webserver has</info>");
        $output->writeln("<info>the required read/write access to the necessary directories & files.</info>");

        return Command::SUCCESS;
    }

    protected function restoreEnv(string $extractDir, string $appDir, OutputInterface $output, InteractiveConsole $interactions): array
    {
        $oldEnv = EnvironmentLoader::load($extractDir);
        $currentEnv = EnvironmentLoader::load($appDir);
        $envContents = file_get_contents(Paths::join($extractDir, '.env'));
        $appEnvPath = Paths::real(Paths::join($appDir, '.env'));

        $mysqlCurrent = MySqlRunner::fromEnvOptions($currentEnv);
        $mysqlOld = MySqlRunner::fromEnvOptions($oldEnv);
        if (!$mysqlOld->testConnection()) {
            $currentWorking = $mysqlCurrent->testConnection();
            if (!$currentWorking) {
                throw new CommandError("Could not find a working database configuration");
            }

            // Copy across new env details to old env
            $currentEnvContents = file_get_contents($appEnvPath);
            $currentEnvDbLines = array_values(array_filter(explode("\n", $currentEnvContents), function (string $line) {
                return str_starts_with($line, 'DB_');
            }));
            $oldEnvLines = array_values(array_filter(explode("\n", $envContents), function (string $line) {
                return !str_starts_with($line, 'DB_');
            }));
            $envContents = implode("\n", [
                '# Database credentials merged from existing .env file',
                ...$currentEnvDbLines,
                ...$oldEnvLines
            ]);
            copy($appEnvPath, $appEnvPath . '.backup');
        }

        $oldUrl = $oldEnv['APP_URL'] ?? '';
        $newUrl = $currentEnv['APP_URL'] ?? '';
        $returnData = [
            'old_url' => $oldUrl,
            'new_url' => $oldUrl,
        ];

        if ($oldUrl !== $newUrl) {
            $question = 'Found different APP_URL values, which would you like to use?';
            $changedUrl = $interactions->choice($question, array_filter([$oldUrl, $newUrl]));
            $envContents = preg_replace('/^APP_URL=.*?$/m', 'APP_URL="' . $changedUrl . '"', $envContents);
            $returnData['new_url'] = $changedUrl;
        }

        file_put_contents($appEnvPath, $envContents);

        return $returnData;
    }

    protected function restoreFolder(string $folderSubPath, string $appDir, string $extractDir): void
    {
        $fullAppFolderPath = Paths::real(Paths::join($appDir, $folderSubPath));
        Directories::delete($fullAppFolderPath);
        Directories::move(Paths::join($extractDir, $folderSubPath), $fullAppFolderPath);
    }

    protected function restoreDatabase(string $appDir, string $extractDir): void
    {
        $dbDump = Paths::join($extractDir, 'db.sql');
        $currentEnv = EnvironmentLoader::load($appDir);
        $mysql = MySqlRunner::fromEnvOptions($currentEnv);

        // Drop existing tables
        $dropSqlTempFile = tempnam(sys_get_temp_dir(), 'bs-cli-restore');
        file_put_contents($dropSqlTempFile, $mysql->dropTablesSql());
        $mysql->importSqlFile($dropSqlTempFile);

        // Import MySQL dump
        $mysql->importSqlFile($dbDump);
    }
}
