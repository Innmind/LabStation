<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\CodingStandard,
    Trigger,
    Triggers,
    Activity,
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
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set as DataSet,
};

class CodingStandardTest extends TestCase
{
    use BlackBox;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new CodingStandard(
                new Iteration,
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new CodingStandard(new Iteration);

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
            Set::of(Triggers::codingStandard),
        ));
    }

    public function testDoNothingWhenToolNotInstalled()
    {
        $trigger = new CodingStandard(new Iteration);

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
            Set::of(Triggers::codingStandard),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new CodingStandard(new Iteration);

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
        $this
            ->forAll(DataSet\Elements::of(
                Activity::sourcesModified,
                Activity::proofsModified,
                Activity::testsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->then(function($activity) {
                $trigger = new CodingStandard(
                    $iteration = new Iteration,
                );

                $os = $this->createMock(OperatingSystem::class);
                $server = $this->createMock(Server::class);
                $processes = $this->createMock(Processes::class);
                $filesystem = $this->createMock(Filesystem::class);
                $adapter = Adapter\InMemory::new();
                $adapter->add(File::named(
                    '.php_cs.dist',
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
                $cs = $this->createMock(Process::class);
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
                    ->willReturnCallback(function($command) use ($matcher, $cs, $say) {
                        match ($matcher->numberOfInvocations()) {
                            1 => $this->assertSame(
                                "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                                $command->toString(),
                            ),
                            2 => $this->assertSame(
                                "say 'Coding Standard : right'",
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
                            1 => $cs,
                            2 => $say,
                        };
                    });
                $cs
                    ->expects($this->once())
                    ->method('output')
                    ->willReturn(new Output\Output(Sequence::of(
                        [Str::of('some output'), Output\Type::output],
                        [Str::of('some error'), Output\Type::error],
                    )));
                $cs
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
                    $activity,
                    Set::of(Triggers::codingStandard),
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
            });
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );

        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            '.php_cs.dist',
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
        $cs = $this->createMock(Process::class);
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
            ->willReturnCallback(function($command) use ($matcher, $cs, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Coding Standard : right'",
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
                    1 => $cs,
                    2 => $say,
                };
            });
        $cs
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $cs
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
            Set::of(Triggers::codingStandard),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }

    public function testTriggerForPHPCSFixer3()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );

        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            '.php-cs-fixer.dist.php',
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
        $cs = $this->createMock(Process::class);
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
            ->willReturnCallback(function($command) use ($matcher, $cs, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Coding Standard : right'",
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
                    1 => $cs,
                    2 => $say,
                };
            });
        $cs
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of(
                [Str::of('some output'), Output\Type::output],
                [Str::of('some error'), Output\Type::error],
            )));
        $cs
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
            Set::of(Triggers::codingStandard),
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
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );

        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            '.php_cs.dist',
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
        $cs = $this->createMock(Process::class);
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
            ->willReturnCallback(function($command) use ($matcher, $cs, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'Coding Standard : wrong'",
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
                    1 => $cs,
                    2 => $say,
                };
            });
        $cs
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $cs
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
            Set::of(Triggers::codingStandard),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );

        $os = $this->createMock(OperatingSystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $filesystem = $this->createMock(Filesystem::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            '.php_cs.dist',
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
                return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
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
            Set::of(Triggers::codingStandard),
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
