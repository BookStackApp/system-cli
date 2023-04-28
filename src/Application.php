<?php
declare(strict_types=1);

namespace Cli;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;

class Application extends BaseApplication
{
    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand()];
    }
}