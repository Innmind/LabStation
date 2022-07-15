<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\ComposerUpdate,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\Server\Control\Server\{
    Processes,
    Process,
    Process\Output,
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
};
use PHPUnit\Framework\TestCase;

class ComposerUpdateTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new ComposerUpdate(
                $this->createMock(Processes::class),
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
        );
        $processes
            ->expects($this->never())
            ->method('execute');
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
            new Activity(Type::sourcesModified),
            $console,
        ));
    }

    public function testTriggerUpdateOnStart()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
        );
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
            new Activity(Type::start),
            $console,
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] ', 'some output', "Dependencies updated!\n"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            ['some error'],
            $console->environment()->errors(),
        );
    }

    public function testDoesntTriggerUpdateWhenNegativeResponse()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
        );
        $processes
            ->expects($this->never())
            ->method('execute');
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
            new Activity(Type::start),
            $console,
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] '],
            $console->environment()->outputs(),
        );
    }
}
