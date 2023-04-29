<?php declare(strict_types=1);

/**
 * This file builds a phar archive to contain our CLI code.
 * Attribution to https://blog.programster.org/creating-phar-files
 * for the code in this file.
 */

try {
    $pharFile = 'app.phar';

    // clean up
    if (file_exists($pharFile)) {
        unlink($pharFile);
    }

    if (file_exists($pharFile . '.gz')) {
        unlink($pharFile . '.gz');
    }

    if (is_dir(__DIR__ . '/vendor/phpunit')) {
        throw new Exception("You should only compile when dev dependencies are NOT installed");
    }

    // create phar
    $phar = new Phar($pharFile);

    // start buffering. Mandatory to modify stub to add shebang
    $phar->startBuffering();

    // Create the default stub from main.php entrypoint
    $defaultStub = $phar->createDefaultStub('run.php');

    // Add the rest of the apps files
    $phar->addFile(__DIR__ . '/run.php', 'run.php');
    $phar->buildFromDirectory(__DIR__, '/src(.*)/');
    $phar->buildFromDirectory(__DIR__, '/vendor(.*)\.php$/');

    // Customize the stub to add the shebang
    $stub = "#!/usr/bin/env php \n" . $defaultStub;

    // Add the stub
    $phar->setStub($stub);

    $phar->stopBuffering();

    // plus - compressing it into gzip
    $phar->compressFiles(Phar::GZ);

    # Make the file executable
    chmod(__DIR__ . "/{$pharFile}", 0775);

    echo "$pharFile successfully created" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
