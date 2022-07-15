<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Tests,
    Trigger,
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
use Innmind\Immutable\{
    Sequence,
    Str,
    Either,
    SideEffect,
    Map,
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
            new Tests($this->createMock(Processes::class), new Iteration),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Tests(
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
        ));
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
                $trigger = new Tests(
                    $processes = $this->createMock(Processes::class),
                    $iteration = new Iteration,
                );
                $processes
                    ->expects($this->exactly(2))
                    ->method('execute')
                    ->withConsecutive(
                        [$this->callback(static function($command): bool {
                            return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                                '/somewhere/' === $command->workingDirectory()->match(
                                    static fn($path) => $path->toString(),
                                    static fn() => null,
                                );
                        })],
                        [$this->callback(static function($command): bool {
                            return $command->toString() === "say 'PHPUnit : ok'";
                        })],
                    )
                    ->will($this->onConsecutiveCalls(
                        $tests = $this->createMock(Process::class),
                        $say = $this->createMock(Process::class),
                    ));
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
            });
    }

    public function testDoesntClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'PHPUnit : ok'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $tests = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                        '/somewhere/' === $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'PHPUnit : failing'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $tests = $this->createMock(Process::class),
                $say = $this->createMock(Process::class),
            ));
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
        );
        $console = $iteration->end($console);
        $this->assertSame([], $console->environment()->outputs());
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
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
            new Activity(Type::sourcesModified),
            $console,
        );
        $console = $iteration->end($console);
        $this->assertSame(["\033[2J\033[H"], $console->environment()->outputs());
        $this->assertSame([], $console->environment()->errors());
    }
}
