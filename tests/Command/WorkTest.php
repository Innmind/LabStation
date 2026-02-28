<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Command;

use Innmind\LabStation\{
    Command\Work,
    Monitor,
    Trigger,
    Iteration,
};
use Innmind\CLI\Command;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class WorkTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Work(
                new Monitor(
                    OperatingSystem::new(),
                    new Iteration,
                    new Trigger\All,
                ),
            ),
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            <<<USAGE
            work --silent --keep-output --triggers= --help --no-interaction

            The triggers option can contain a comma separated list of values.

            Triggers can contain : cs, composer, docker, psalm and tests
            USAGE,
            (new Work(
                new Monitor(
                    OperatingSystem::new(),
                    new Iteration,
                    new Trigger\All,
                ),
            ))->usage()->toString(),
        );
    }
}
