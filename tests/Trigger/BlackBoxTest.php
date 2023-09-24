<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\BlackBox,
    Trigger,
    Triggers,
    Activity,
    Activity\Type,
    Iteration,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\{
    Processes,
    Process,
    Process\Output,
    Process\ExitCode,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Filesystem\{
    Adapter,
    File\File,
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
    PHPUnit\BlackBox as BB,
    Set,
};

class BlackBoxTest extends TestCase
{
    use BB;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new BlackBox(
                $this->createMock(Filesystem::class),
                $this->createMock(Processes::class),
                new Iteration,
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new BlackBox(
            $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            new Iteration,
        );
        $processes
            ->expects($this->never())
            ->method('execute');
        $console = Console::of(
            $this->createMock(Environment::class),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            new Activity(Type::start),
            $console,
            ISet::of(Triggers::tests),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $this
            ->forAll(Set\Elements::of(
                Type::sourcesModified,
                Type::testsModified,
                Type::fixturesModified,
                Type::propertiesModified,
            ))
            ->then(function($type) {
                $trigger = new BlackBox(
                    $filesystem = $this->createMock(Filesystem::class),
                    $processes = $this->createMock(Processes::class),
                    new Iteration,
                );
                $filesystem
                    ->expects($this->never())
                    ->method('mount');
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

                $console = $trigger(
                    new Activity($type),
                    $console,
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
                Type::sourcesModified,
                Type::testsModified,
                Type::fixturesModified,
                Type::propertiesModified,
            ))
            ->then(function($type) {
                $trigger = new BlackBox(
                    $filesystem = $this->createMock(Filesystem::class),
                    $processes = $this->createMock(Processes::class),
                    $iteration = new Iteration,
                );
                $adapter = Adapter\InMemory::new();
                $adapter->add(File::named(
                    'blackbox.php',
                    Content\None::of(),
                ));
                $filesystem
                    ->expects($this->once())
                    ->method('mount')
                    ->willReturn($adapter);
                $tests = $this->createMock(Process::class);
                $say = $this->createMock(Process::class);
                $processes
                    ->expects($matcher = $this->exactly(2))
                    ->method('execute')
                    ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                        match ($matcher->numberOfInvocations()) {
                            1 => $this->assertSame(
                                "php 'blackbox.php'",
                                $command->toString(),
                            ),
                            2 => $this->assertSame(
                                "say 'BlackBox : ok'",
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
                    new Activity($type),
                    $console,
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
                Type::sourcesModified,
                Type::testsModified,
                Type::fixturesModified,
                Type::propertiesModified,
            ))
            ->then(function($type) {
                $trigger = new BlackBox(
                    $filesystem = $this->createMock(Filesystem::class),
                    $processes = $this->createMock(Processes::class),
                    $iteration = new Iteration,
                );
                $adapter = Adapter\InMemory::new();
                $filesystem
                    ->expects($this->once())
                    ->method('mount')
                    ->willReturn($adapter);
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
                    new Activity($type),
                    $console,
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
        $trigger = new BlackBox(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'blackbox.php',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($adapter);
        $tests = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "php 'blackbox.php'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'BlackBox : ok'",
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
            new Activity(Type::sourcesModified),
            $console,
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new BlackBox(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'blackbox.php',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($adapter);
        $tests = $this->createMock(Process::class);
        $say = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $tests, $say) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(
                        "php 'blackbox.php'",
                        $command->toString(),
                    ),
                    2 => $this->assertSame(
                        "say 'BlackBox : failing'",
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
            new Activity(Type::sourcesModified),
            $console,
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new BlackBox(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'blackbox.php',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($adapter);
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "php 'blackbox.php'" &&
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
            new Activity(Type::sourcesModified),
            $console,
            ISet::of(Triggers::tests),
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
