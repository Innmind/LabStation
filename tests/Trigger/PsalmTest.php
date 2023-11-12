<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Psalm,
    Trigger,
    Triggers,
    Activity,
    Activity\Type,
    Iteration,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\Output,
    Server\Process\ExitCode,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Url\Path;
use Innmind\OperatingSystem\{
    OperatingSystem,
    Filesystem,
};
use Innmind\Filesystem\{
    Adapter,
    Name,
    File,
    File\Content,
};
use Innmind\Immutable\{
    Sequence,
    Str,
    Either,
    SideEffect,
    Map,
    Set,
};
use PHPUnit\Framework\TestCase;

class PsalmTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Psalm(new Iteration),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Psalm(new Iteration);
        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('filesystem');
        $os
            ->expects($this->never())
            ->method('control');
        $console = Console::of(
            $this->createMock(Environment::class),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenPsalmNotInstalled()
    {
        $trigger = new Psalm(new Iteration);
        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn(Adapter\InMemory::new());
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
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new Psalm(new Iteration);
        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('filesystem');
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

        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
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

    public function testTriggerTestsSuiteWhenSourcesModified()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $psalm = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $psalm, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/psalm '--no-cache'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Psalm : ok'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $psalm,
                    2 => $say,
                };
            });
        $psalm
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of(
                [Str::of('some output'), Output\Type::output],
                [Str::of('some error'), Output\Type::error],
            )));
        // we say here that psalm is successful even though we have an error in
        // the output in order to verify the terminal is cleared on success
        $psalm
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $say
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ['some output', 'some error', "\033[2J\033[H"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            [],
            $console->environment()->errors(),
        );
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $psalm = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $psalm, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/psalm '--no-cache'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Psalm : ok'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $psalm,
                    2 => $say,
                };
            });
        $psalm
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $psalm
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $say
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $console = Console::of(
            Environment\InMemory::of(
                [],
                true,
                ['--keep-output'],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options(Map::of(['keep-output', ''])),
        );

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testTriggerTestsSuiteWhenTestsModified()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $psalm = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $psalm, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/psalm '--no-cache'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Psalm : ok'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $psalm,
                    2 => $say,
                };
            });
        $psalm
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of(
                [Str::of('some output'), Output\Type::output],
                [Str::of('some error'), Output\Type::error],
            )));
        // we say here that psalm is successful even though we have an error in
        // the output in order to verify the terminal is cleared on success
        $psalm
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $say
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::testsModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ['some output', 'some error', "\033[2J\033[H"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            [],
            $console->environment()->errors(),
        );
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $psalm = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $psalm, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/psalm '--no-cache'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Psalm : failing'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $psalm,
                    2 => $say,
                };
            });
        $psalm
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $psalm
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::left(new ExitCode(1)));
        $say
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
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
                return $command->toString() === "vendor/bin/psalm '--no-cache'" &&
                    '/somewhere/' === $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    );
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $console = Console::of(
            Environment\InMemory::of(
                [],
                true,
                ['--silent'],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options(Map::of(['silent', ''])),
        );

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
