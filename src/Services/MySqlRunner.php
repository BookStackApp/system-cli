<?php declare(strict_types=1);

namespace Cli\Services;

use Exception;

class MySqlRunner
{
    public function __construct(
        protected string $host,
        protected string $user,
        protected string $password,
        protected string $database,
        protected int $port = 3306
    ) {
    }

    /**
     * @throws Exception
     */
    public function ensureOptionsSet(): void
    {
        $options = ['host', 'user', 'password', 'database'];
        foreach ($options as $option) {
            if (!$this->$option) {
                throw new Exception("Could not find a valid value for the \"{$option}\" database option.");
            }
        }
    }

    public function testConnection(): bool
    {
        $output = (new ProgramRunner('mysql', '/usr/bin/mysql'))
            ->withEnvironment(['MYSQL_PWD' => $this->password])
            ->withTimeout(300)
            ->withIdleTimeout(300)
            ->runCapturingStdErr([
                '-h', $this->host,
                '-P', $this->port,
                '-u', $this->user,
                '--protocol=TCP',
                $this->database,
                '-e', "show tables;"
            ]);

        return !$output;
    }

    public function importSqlFile(string $sqlFilePath): void
    {
        $output = (new ProgramRunner('mysql', '/usr/bin/mysql'))
            ->withEnvironment(['MYSQL_PWD' => $this->password])
            ->withTimeout(300)
            ->withIdleTimeout(300)
            ->runCapturingStdErr([
                '-h', $this->host,
                '-P', $this->port,
                '-u', $this->user,
                '--protocol=TCP',
                $this->database,
                '-e', "source {$sqlFilePath}"
            ]);

        if ($output) {
            throw new Exception("Failed mysql file import with errors:\n" . $output);
        }
    }

    public function dropTablesSql(): string
    {
        return <<<'HEREDOC'
SET FOREIGN_KEY_CHECKS = 0;
SET GROUP_CONCAT_MAX_LEN=32768;
SET @tables = NULL;
SELECT GROUP_CONCAT('`', table_name, '`') INTO @tables
  FROM information_schema.tables
  WHERE table_schema = (SELECT DATABASE());
SELECT IFNULL(@tables,'dummy') INTO @tables;

SET @tables = CONCAT('DROP TABLE IF EXISTS ', @tables);
PREPARE stmt FROM @tables;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS = 1;
HEREDOC;
    }

    public function runDumpToFile(string $filePath): string
    {
        $file = fopen($filePath, 'w');
        $errors = "";
        $warnings = "";
        $hasOutput = false;

        try {
            (new ProgramRunner('mysqldump', '/usr/bin/mysqldump'))
                ->withTimeout(300)
                ->withIdleTimeout(300)
                ->withEnvironment(['MYSQL_PWD' => $this->password])
                ->runWithoutOutputCallbacks([
                    '-h', $this->host,
                    '-P', $this->port,
                    '-u', $this->user,
                    '--protocol=TCP',
                    '--single-transaction',
                    '--no-tablespaces',
                    $this->database,
                ], function ($data) use (&$file, &$hasOutput) {
                    fwrite($file, $data);
                    $hasOutput = true;
                }, function ($error) use (&$errors, &$warnings) {
                    $lines = explode("\n", $error);
                    foreach ($lines as $line) {
                        if (str_starts_with(strtolower($line), 'warning: ')) {
                            $warnings .= $line;
                        } else {
                            $errors .= $line . "\n";
                        }
                    }
                });
        } catch (\Exception $exception) {
            fclose($file);
            if ($exception instanceof ProcessTimedOutException) {
                if (!$hasOutput) {
                    throw new Exception("mysqldump operation timed-out.\nNo data has been received so the connection to your database may have failed.");
                } else {
                    throw new Exception("mysqldump operation timed-out after data was received.");
                }
            }
            throw new Exception($exception->getMessage());
        }

        fclose($file);

        if ($errors) {
            throw new Exception("Failed mysqldump with errors:\n" . $errors);
        }

        return $warnings;
    }

    public static function fromEnvOptions(array $env): static
    {
        $host = ($env['DB_HOST'] ?? '');
        $username = ($env['DB_USERNAME'] ?? '');
        $password = ($env['DB_PASSWORD'] ?? '');
        $database = ($env['DB_DATABASE'] ?? '');
        $port = intval($env['DB_PORT'] ?? 3306);

        return new static($host, $username, $password, $database, $port);
    }
}