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
use PHPUnit\Framework\TestCase;

class WorkTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Work(
                new Monitor(
                    $this->createMock(OperatingSystem::class),
                    new Iteration,
                    $this->createMock(Trigger::class),
                ),
            ),
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            <<<USAGE
            work --silent --keep-output --triggers=

            The triggers option can contain a comma separated list of values.

            Triggers can contain : cs, composer, docker, psalm and tests
            USAGE,
            (new Work(
                new Monitor(
                    $this->createMock(OperatingSystem::class),
                    new Iteration,
                    $this->createMock(Trigger::class),
                ),
            ))->usage(),
        );
    }
}
