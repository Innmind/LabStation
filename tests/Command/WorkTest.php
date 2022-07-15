<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Command;

use Innmind\LabStation\{
    Command\Work,
    Monitor,
    Protocol,
    Trigger,
    Iteration,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Console,
};
use Innmind\ProcessManager\{
    Manager,
    Running,
};
use Innmind\IPC\{
    IPC,
    Process\Name,
    Server,
};
use Innmind\Immutable\{
    Either,
    SideEffect,
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
                    Name::of('foo'),
                    new Iteration,
                    $this->createMock(Trigger::class),
                ),
            ),
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            'work --silent --keep-output',
            (new Work(
                new Monitor(
                    $this->createMock(Protocol::class),
                    $this->createMock(Manager::class),
                    $this->createMock(IPC::class),
                    Name::of('foo'),
                    new Iteration,
                    $this->createMock(Trigger::class),
                ),
            ))->usage(),
        );
    }

    public function testInvokation()
    {
        $command = new Work(
            new Monitor(
                $this->createMock(Protocol::class),
                $manager = $this->createMock(Manager::class),
                $ipc = $this->createMock(IPC::class),
                Name::of('foo'),
                new Iteration,
                $trigger = $this->createMock(Trigger::class),
            ),
        );
        $console = Console::of(
            Environment\InMemory::of(
                [],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );
        $manager
            ->expects($this->once())
            ->method('start')
            ->willReturn(Either::right($running = $this->createMock(Running::class)));
        $running
            ->expects($this->once())
            ->method('kill')
            ->willReturn(Either::right(new SideEffect));
        $ipc
            ->expects($this->once())
            ->method('listen')
            ->willReturn($server = $this->createMock(Server::class));
        $server
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn(Either::right($console));
        $trigger
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnArgument(1));

        $console = $command($console);
        // It says it crashed because it's never supposed to terminate
        $this->assertSame(["Crashed\n"], $console->environment()->errors());
    }
}
