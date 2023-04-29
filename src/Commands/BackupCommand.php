<?php declare(strict_types=1);

namespace Cli\Commands;

use Cli\Services\AppLocator;
use Cli\Services\EnvironmentLoader;
use Cli\Services\MySqlRunner;
use Cli\Services\Paths;
use RecursiveDirectoryIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

final class BackupCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('backup');
        $this->setDescription('Backup a BookStack installation to a single compressed ZIP file.');
        $this->addArgument('backup-path', InputArgument::OPTIONAL, 'Outfile file or directory to store the resulting backup file.', '');
        $this->addOption('no-database', null, InputOption::VALUE_NONE, "Skip adding a database dump to the backup");
        $this->addOption('no-uploads', null, InputOption::VALUE_NONE, "Skip adding uploaded files to the backup");
        $this->addOption('no-themes', null, InputOption::VALUE_NONE, "Skip adding the themes folder to the backup");
        $this->addOption('app-directory', null, InputOption::VALUE_OPTIONAL, 'BookStack install directory to backup', '');
    }

    /**
     * @throws CommandError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appDir = AppLocator::require($input->getOption('app-directory'));
        $output->writeln("<info>Checking system requirements...</info>");
        $this->ensureRequiredExtensionInstalled();

        $handleDatabase = !$input->getOption('no-database');
        $handleUploads = !$input->getOption('no-uploads');
        $handleThemes = !$input->getOption('no-themes');
        $suggestedOutPath = $input->getArgument('backup-path');

        $zipOutFile = $this->buildZipFilePath($suggestedOutPath, $appDir);

        // Create a new ZIP file
        $zipTempFile = tempnam(sys_get_temp_dir(), 'bsbackup');
        $dumpTempFile = '';
        $zip = new ZipArchive();
        $zip->open($zipTempFile, ZipArchive::OVERWRITE);

        // Add default files (.env config file and this CLI if existing)
        $zip->addFile(Paths::join($appDir, '.env'), '.env');
        $cliPath = Paths::join($appDir, 'bookstack-system-cli');
        if (file_exists($cliPath)) {
            $zip->addFile($cliPath, 'bookstack-system-cli');
        }

        if ($handleDatabase) {
            $output->writeln("<info>Dumping the database via mysqldump...</info>");
            $dumpTempFile = $this->createDatabaseDump($appDir, $output);
            $output->writeln("<info>Adding database dump to backup archive...</info>");
            $zip->addFile($dumpTempFile, 'db.sql');
        }

        if ($handleUploads) {
            $output->writeln("<info>Adding BookStack upload folders to backup archive...</info>");
            $this->addUploadFoldersToZip($zip, $appDir);
        }

        if ($handleThemes) {
            $output->writeln("<info>Adding BookStack theme folders to backup archive...</info>");
            $this->addFolderToZipRecursive($zip, Paths::join($appDir, 'themes'), 'themes');
        }

        $output->writeln("<info>Saving backup archive...</info>");
        // Close off our zip and move it to the required location
        $zip->close();
        // Delete our temporary DB dump file if exists. Must be done after zip close.
        if ($dumpTempFile) {
            unlink($dumpTempFile);
        }
        // Move the zip into the target location
        rename($zipTempFile, $zipOutFile);

        // Announce end
        $output->writeln("<success>Backup finished.</success>");
        $output->writeln("Output ZIP saved to: {$zipOutFile}");

        return Command::SUCCESS;
    }

    /**
     * Ensure the required PHP extensions are installed for this command.
     * @throws CommandError
     */
    protected function ensureRequiredExtensionInstalled(): void
    {
        if (!extension_loaded('zip')) {
            throw new CommandError('The "zip" PHP extension is required to run this command');
        }
    }

    /**
     * Build a full zip path from the given suggestion, which may be empty,
     * a path to a folder, or a path to a file in relative or absolute form.
     * Targets the <app>/backups directory by default if existing, otherwise <app>.
     * @throws CommandError
     */
    protected function buildZipFilePath(string $suggestedOutPath, string $appDir): string
    {
        $zipDir = Paths::join($appDir, 'storage', 'backups');
        $zipName = "bookstack-backup-" . date('Y-m-d-His') . '.zip';

        if ($suggestedOutPath) {
            $suggestedOutPath = Paths::resolve($suggestedOutPath);
            if (is_dir($suggestedOutPath)) {
                $zipDir = realpath($suggestedOutPath);
            } else if (is_dir(dirname($suggestedOutPath))) {
                $zipDir = realpath(dirname($suggestedOutPath));
                $zipName = basename($suggestedOutPath);
            } else {
                throw new CommandError("Could not resolve provided [{$suggestedOutPath}] path to an existing folder.");
            }
        } else {
            if (!is_dir($zipDir)) {
                $zipDir = $appDir;
            }
        }

        $fullPath = Paths::join($zipDir, $zipName);

        if (file_exists($fullPath)) {
            throw new CommandError("Target ZIP output location at [{$fullPath}] already exists.");
        } else if (!is_dir($zipDir)) {
            throw new CommandError("Target ZIP output directory [{$fullPath}] could not be found.");
        } else if (!is_writable($zipDir)) {
            throw new CommandError("Target ZIP output directory [{$fullPath}] is not writable.");
        }

        return $fullPath;
    }

    /**
     * Add app-relative upload folders to the provided zip archive.
     * Will recursively go through all directories to add all files.
     */
    protected function addUploadFoldersToZip(ZipArchive $zip, string $appDir): void
    {
        $this->addFolderToZipRecursive($zip, Paths::join($appDir, 'public', 'uploads'), 'public/uploads');
        $this->addFolderToZipRecursive($zip, Paths::join($appDir, 'storage', 'uploads'), 'storage/uploads');
    }

    /**
     * Recursively add all contents of the given dirPath to the provided zip file
     * with a zip location of the targetZipPath.
     */
    protected function addFolderToZipRecursive(ZipArchive $zip, string $dirPath, string $targetZipPath): void
    {
        $dirIter = new RecursiveDirectoryIterator($dirPath);
        $fileIter = new \RecursiveIteratorIterator($dirIter);
        /** @var SplFileInfo $file */
        foreach ($fileIter as $file) {
            if (!$file->isDir()) {
                $zip->addFile($file->getPathname(), $targetZipPath . '/' . $fileIter->getSubPathname());
            }
        }
    }

    /**
     * Create a database dump and return the path to the dumped SQL output.
     * @throws CommandError
     */
    protected function createDatabaseDump(string $appDir, OutputInterface $output): string
    {
        $envOptions = EnvironmentLoader::loadMergedWithCurrentEnv($appDir);
        $mysql = MySqlRunner::fromEnvOptions($envOptions);
        $mysql->ensureOptionsSet();

        $dumpTempFile = tempnam(sys_get_temp_dir(), 'bsdbdump');
        try {
            $warnings = $mysql->runDumpToFile($dumpTempFile);
            if ($warnings) {
                $output->writeln("<warn>Received warnings during mysqldump:\n{$warnings}</warn>");
            }
        } catch (\Exception $exception) {
            unlink($dumpTempFile);
            throw new CommandError($exception->getMessage());
        }

        return $dumpTempFile;
    }
}
