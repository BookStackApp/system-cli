#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$app = require __DIR__ . '/src/app.php';

try {
    $app->run();
} catch (Exception $error) {
    $output = (new ConsoleOutput())->getErrorOutput();
    $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));
    $output->writeln("<error>\nAn error occurred when attempting to run a command:\n</error>");
    $output->writeln($error->getMessage());
    exit(1);
}
