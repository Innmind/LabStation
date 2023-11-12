<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Tests,
    Trigger,
    Triggers,
    Activity,
    Iteration,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Filesystem,
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
use Innmind\Filesystem\{
    Adapter,
    File,
    File\Content,
};
use Innmind\Immutable\{
    Sequence,
    Str,
    Either,
    SideEffect,
    Map,
    Set as ISet,
};
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class TestsTest extends TestCase
{
    use BlackBox;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Tests(
                new Iteration,
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Tests(new Iteration);
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
            ISet::of(Triggers::tests),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $this
            ->forAll(Set\Elements::of(...Activity::cases()))
            ->then(function($type) {
                $trigger = new Tests(new Iteration);
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
                    $type,
                    ISet::of(),
                );
                $this->assertSame(
                    [],
                    $console->environment()->outputs(),
                );
                $this->assertSame(
                    [],
                    $console->environment()->errors(),
                );
            });
    }

    public function testTriggerTestsSuiteWhenActivity()
    {
        $this
            ->forAll(Set\Elements::of(
                Activity::sourcesModified,
                Activity::testsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->then(function($type) {
                $trigger = new Tests(
                    $iteration = new Iteration,
                );
                $os = $this->createMock(OperatingSystem::class);
                $filesystem = $this->createMock(Filesystem::class);
                $server = $this->createMock(Server::class);
                $processes = $this->createMock(Processes::class);
                $adapter = Adapter\InMemory::new();
                $adapter->add(File::named(
                    'phpunit.xml.dist',
                    Content::none(),
                ));

                $os
                    ->method('filesystem')
                    ->willReturn($filesystem);
                $filesystem
                    ->expects($this->once())
                    ->method('mount')
                    ->willReturn($adapter);
                $os
                    ->method('control')
                    ->willReturn($server);
                $server
                    ->method('processes')
                    ->willReturn($processes);
                $tests = $this->createMock(Process::class);
                $say = $this->createMock(Process::class);
                $processes
                    ->expects($matcher = $this->exactly(2))
                    ->method('execute')
                    ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                        match ($matcher->numberOfInvocations()) {
                            1 => $this->assertSame(
                                "vendor/bin/phpunit '--colors=always' '--fail-on-warning'",
                                $command->toString(),
                            ),
                            2 => $this->assertSame(
                                "say 'PHPUnit : ok'",
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
                            1 => $tests,
                            2 => $say,
                        };
                    });
                $tests
                    ->expects($this->once())
                    ->method('output')
                    ->willReturn(new Output\Output(Sequence::of(
                        [Str::of('some output'), Output\Type::output],
                        [Str::of('some error'), Output\Type::error],
                    )));
                // we say here that tests are successful even though we have an
                // error in the output in order to verify the terminal is cleared
                // on success
                $tests
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
                    $type,
                    ISet::of(Triggers::tests),
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

    public function testDoesntTriggerWhenNoPHPUnitFile()
    {
        $this
            ->forAll(Set\Elements::of(
                Activity::sourcesModified,
                Activity::testsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->then(function($type) {
                $trigger = new Tests(
                    $iteration = new Iteration,
                );
                $os = $this->createMock(OperatingSystem::class);
                $filesystem = $this->createMock(Filesystem::class);
                $server = $this->createMock(Server::class);
                $processes = $this->createMock(Processes::class);
                $adapter = Adapter\InMemory::new();

                $os
                    ->method('filesystem')
                    ->willReturn($filesystem);
                $filesystem
                    ->expects($this->once())
                    ->method('mount')
                    ->willReturn($adapter);
                $os
                    ->method('control')
                    ->willReturn($server);
                $server
                    ->method('processes')
                    ->willReturn($processes);
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

                $iteration->start();
                $console = $trigger(
                    $console,
                    $os,
                    $type,
                    ISet::of(Triggers::tests),
                );
                $console = $iteration->end($console);
                $this->assertSame(
                    ["\033[2J\033[H"],
                    $console->environment()->outputs(),
                );
                $this->assertSame(
                    [],
                    $console->environment()->errors(),
                );
            });
    }

    public function testDoesntClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Tests(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'phpunit.xml.dist',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($adapter);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);

        $tests = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/phpunit '--colors=always' '--fail-on-warning'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'PHPUnit : ok'",
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
                    1 => $tests,
                    2 => $say,
                };
            });
        $tests
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $tests
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
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
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Tests(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'phpunit.xml.dist',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($adapter);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);

        $tests = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "vendor/bin/phpunit '--colors=always' '--fail-on-warning'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'PHPUnit : failing'",
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
                    1 => $tests,
                    2 => $say,
                };
            });
        $tests
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output\Output(Sequence::of()));
        $tests
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
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Tests(
            $iteration = new Iteration,
        );
        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'phpunit.xml.dist',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
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
                return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
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
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
