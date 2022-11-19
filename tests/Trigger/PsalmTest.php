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
use Innmind\Url\Path;
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\{
    Adapter,
    Name,
    File\File,
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
            new Psalm(
                $this->createMock(Processes::class),
                $this->createMock(Filesystem::class),
                new Iteration,
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $this->createMock(Filesystem::class),
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
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenPsalmNotInstalled()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            new Iteration,
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn(Adapter\InMemory::new());
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
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
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
            new Activity(Type::sourcesModified),
            $console,
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
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/psalm '--no-cache'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Psalm : ok'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $psalm = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
            new Activity(Type::sourcesModified),
            $console,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ['some output', "\033[2J\033[H"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            ['some error'],
            $console->environment()->errors(),
        );
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/psalm '--no-cache'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
            )
            ->will($this->onConsecutiveCalls(
                $psalm = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
            new Activity(Type::sourcesModified),
            $console,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testTriggerTestsSuiteWhenTestsModified()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/psalm '--no-cache'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Psalm : ok'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $psalm = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
            new Activity(Type::testsModified),
            $console,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ['some output', "\033[2J\033[H"],
            $console->environment()->outputs(),
        );
        $this->assertSame(
            ['some error'],
            $console->environment()->errors(),
        );
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/psalm '--no-cache'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Psalm : failing'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $psalm = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
            new Activity(Type::sourcesModified),
            $console,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $adapter = Adapter\InMemory::new();
        $adapter->add(File::named(
            'psalm.xml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere/'))
            ->willReturn($adapter);
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
            new Activity(Type::sourcesModified),
            $console,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
