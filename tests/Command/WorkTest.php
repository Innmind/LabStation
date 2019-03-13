<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Command;

use Innmind\LabStation\{
    Command\Work,
    Monitor,
    Protocol,
    Trigger,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\ProcessManager\Manager;
use Innmind\IPC\{
    IPC,
    Process\Name,
};
use PHPUnit\Framework\TestCase;

class WorkTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Work(
                new Monitor(
                    $this->createMock(Protocol::class),
                    $this->createMock(Manager::class),
                    $this->createMock(IPC::class),
                    new Name('foo'),
                    $this->createMock(Trigger::class)
                )
            )
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            'work',
            (string) new Work(
                new Monitor(
                    $this->createMock(Protocol::class),
                    $this->createMock(Manager::class),
                    $this->createMock(IPC::class),
                    new Name('foo'),
                    $this->createMock(Trigger::class)
                )
            )
        );
    }

    public function testInvokation()
    {
        $command = new Work(
            new Monitor(
                $this->createMock(Protocol::class),
                $this->createMock(Manager::class),
                $ipc = $this->createMock(IPC::class),
                new Name('foo'),
                $this->createMock(Trigger::class)
            )
        );
        $ipc
            ->expects($this->once())
            ->method('listen');

        $this->assertNull($command(
            $this->createMock(Environment::class),
            new Arguments,
            new Options
        ));
    }
}
