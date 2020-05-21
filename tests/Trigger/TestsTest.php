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
use Innmind\CLI\Environment;
use Innmind\Stream\Writable;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Sequence,
    Str,
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
            new Tests($this->createMock(Processes::class), new Iteration)
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

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $this->createMock(Environment::class)
        ));
    }

    public function testTriggerTestsSuiteWhenActivity()
    {
        $this
            ->forAll(Set\Elements::of(
                Type::sourcesModified(),
                Type::testsModified(),
                Type::fixturesModified(),
                Type::propertiesModified(),
            ))
            ->then(function($type) {
                $trigger = new Tests(
                    $processes = $this->createMock(Processes::class),
                    $iteration = new Iteration,
                );
                $processes
                    ->expects($this->at(0))
                    ->method('execute')
                    ->with($this->callback(static function($command): bool {
                        return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                            $command->workingDirectory()->toString() === '/somewhere';
                    }))
                    ->willReturn($process = $this->createMock(Process::class));
                $process
                    ->expects($this->once())
                    ->method('output')
                    ->willReturn($output = $this->createMock(Output::class));
                $output
                    ->expects($this->once())
                    ->method('foreach')
                    ->with($this->callback(static function($listen): bool {
                        $listen(Str::of('some output'), Output\Type::output());
                        $listen(Str::of('some error'), Output\Type::error());

                        return true;
                    }));
                $process
                    ->expects($this->once())
                    ->method('wait')
                    ->will($this->returnSelf());
                $process
                    ->expects($this->once())
                    ->method('exitCode')
                    ->willReturn(new ExitCode(0));
                $processes
                    ->expects($this->at(1))
                    ->method('execute')
                    ->with($this->callback(static function($command): bool {
                        return $command->toString() === "say 'PHPUnit : ok'";
                    }));
                $env = $this->createMock(Environment::class);
                $env
                    ->expects($this->any())
                    ->method('arguments')
                    ->willReturn(Sequence::strings());
                $env
                    ->expects($this->once())
                    ->method('workingDirectory')
                    ->willReturn(Path::of('/somewhere'));
                $env
                    ->expects($this->any())
                    ->method('output')
                    ->willReturn($output = $this->createMock(Writable::class));
                $env
                    ->expects($this->once())
                    ->method('error')
                    ->willReturn($error = $this->createMock(Writable::class));
                $output
                    ->expects($this->at(0))
                    ->method('write')
                    ->with(Str::of('some output'));
                $output
                    ->expects($this->at(1))
                    ->method('write')
                    ->with(Str::of("\033[2J\033[H"));
                $error
                    ->expects($this->once())
                    ->method('write')
                    ->with(Str::of('some error'));

                $iteration->start();
                $this->assertNull($trigger(
                    new Activity($type, []),
                    $env
                ));
                $iteration->end($env);
            });
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                    $command->workingDirectory()->toString() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "say 'PHPUnit : ok'";
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings('--keep-output'));
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->never())
            ->method('write');

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
        $iteration->end($env);
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                    $command->workingDirectory()->toString() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('foreach')
            ->with($this->callback(static function($listen): bool {
                return true;
            }));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(1));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "say 'PHPUnit : failing'";
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->never())
            ->method('write');

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
        $iteration->end($env);
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class),
            $iteration = new Iteration,
        );
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/phpunit '--colors=always' '--fail-on-warning'" &&
                    $command->workingDirectory()->toString() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('foreach');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::of('string', '--silent'));

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
        $iteration->end($env);
    }
}
