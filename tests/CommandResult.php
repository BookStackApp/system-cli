<?php

namespace Tests;

use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Tester\CommandTester;

class CommandResult
{

    public function __construct(
        protected CommandTester $tester,
        protected ?\Exception $error
    ) { }

    public function getStderr(): string
    {
        return $this->error?->getMessage() ?? '';
    }

    public function assertSuccessfulExit(): void
    {
        Assert::assertEquals(0, $this->tester->getStatusCode());
    }

    public function assertErrorExit(): void
    {
        Assert::assertTrue($this->error !== null);
    }

    public function assertStdoutContains(string $needle): void
    {
        Assert::assertStringContainsString($needle, $this->tester->getDisplay());
    }

    public function assertStderrContains(string $needle): void
    {
        Assert::assertStringContainsString($needle, $this->getStderr());
    }


}