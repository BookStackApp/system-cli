#!/usr/bin/env php
<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

// Get the app with commands loaded
$app = require __DIR__ . '/src/app.php';

// Configure output formatting
$output =  new ConsoleOutput();
$formatter = $output->getFormatter();
$formatter->setStyle('warn', new OutputFormatterStyle('yellow'));
$formatter->setStyle('info', new OutputFormatterStyle('cyan'));
$formatter->setStyle('success', new OutputFormatterStyle('green'));
$formatter->setStyle('error', new OutputFormatterStyle('red'));

// Run the command and handle errors
try {
    $output->writeln("<warn>WARNING: This CLI is in early alpha testing.</warn>");
    $output->writeln("<warn>There's a high chance of running into bugs, and the CLI API is subject to change.</warn>");
    $output->writeln("");

    $app->run(null, $output);
} catch (Exception $error) {
    $output = (new ConsoleOutput())->getErrorOutput();
    $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));
    $output->writeln("<error>\nAn error occurred when attempting to run a command:\n</error>");
    $output->writeln($error->getMessage());
    exit(1);
}
