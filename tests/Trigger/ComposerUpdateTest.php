<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\ComposerUpdate,
    Trigger,
    Triggers,
    Activity,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\Output,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Immutable\{
    Str,
    Sequence,
    Set,
};
use PHPUnit\Framework\TestCase;

class ComposerUpdateTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new ComposerUpdate,
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new ComposerUpdate;

        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('control');
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

        $this->assertSame($console, $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::composerUpdate),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new ComposerUpdate;

        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('control');
        $console = Console::of(
            Environment\InMemory::of(
                ["\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(),
        );
        $this->assertSame(
            [],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            [],
            $console->environment()->errors(),
        );
    }

    public function testTriggerUpdateOnStart()
    {
        $trigger = new ComposerUpdate;

        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);

        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "composer '--ansi' 'update'" &&
                    '/somewhere/' === $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    );
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of(
                [Str::of('some output'), Output\Type::output],
                [Str::of('some error'), Output\Type::error],
            )));
        $console = Console::of(
            Environment\InMemory::of(
                ["\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::composerUpdate),
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] ', 'some output', 'some error', "Dependencies updated!\n"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            [],
            $console->environment()->errors(),
        );
    }

    public function testDoesntTriggerUpdateWhenNegativeResponse()
    {
        $trigger = new ComposerUpdate;

        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('control');
        $console = Console::of(
            Environment\InMemory::of(
                ["n\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::composerUpdate),
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] '],
            $console->environment()->outputs(),
        );
    }
}
