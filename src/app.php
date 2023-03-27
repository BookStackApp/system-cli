<?php

use Cli\Commands\BackupCommand;
use Cli\Commands\InitCommand;
use Cli\Commands\RestoreCommand;
use Cli\Commands\UpdateCommand;
use Symfony\Component\Console\Application;

// Setup our CLI
$app = new Application('bookstack-system');
$app->setCatchExceptions(false);

$app->add(new BackupCommand());
$app->add(new UpdateCommand());
$app->add(new InitCommand());
$app->add(new RestoreCommand());

return $app;