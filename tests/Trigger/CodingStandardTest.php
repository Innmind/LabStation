<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\CodingStandard,
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
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\{
    Adapter,
    Name,
};
use Innmind\Immutable\{
    Sequence,
    Str,
};
use PHPUnit\Framework\TestCase;

class CodingStandardTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new CodingStandard(
                $this->createMock(Processes::class),
                $this->createMock(Filesystem::class),
                new Iteration,
            )
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $this->createMock(Filesystem::class),
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

    public function testDoNothingWhenPsalmNotInstalled()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            new Iteration,
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php-cs-fixer.dist.php')],
            )
            ->will($this->onConsecutiveCalls(false, false));
        $processes
            ->expects($this->never())
            ->method('execute');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn(Path::none());

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env,
        ));
    }

    public function testTriggerTestsSuiteWhenSourcesModified()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(true, true));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Coding Standard : right'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process = $this->createMock(Process::class),
                $this->createMock(Process::class),
            ));
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of('some output')],
                [Str::of("\033[2J\033[H")],
            );
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
        $iteration->end($env);
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(true, true));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive([$this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
                    $command->workingDirectory()->toString() === '/somewhere';
            })])
            ->will($this->onConsecutiveCalls(
                $process = $this->createMock(Process::class),
                $this->createMock(Process::class),
            ));
        $process
            ->expects($this->once())
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings('--keep-output'));
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
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

    public function testTriggerTestsSuiteWhenTestsModified()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(true, true));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Coding Standard : right'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process = $this->createMock(Process::class),
                $this->createMock(Process::class),
            ));
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of('some output')],
                [Str::of("\033[2J\033[H")],
            );
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::testsModified(), []),
            $env
        ));
        $iteration->end($env);
    }

    public function testTriggerForPHPCSFixer3()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(3))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php-cs-fixer.dist.php')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(false, true, false));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Coding Standard : right'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process = $this->createMock(Process::class),
                $this->createMock(Process::class),
            ));
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of('some output')],
                [Str::of("\033[2J\033[H")],
            );
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $iteration->start();
        $this->assertNull($trigger(
            new Activity(Type::testsModified(), []),
            $env
        ));
        $iteration->end($env);
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(true, true));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "say 'Coding Standard : wrong'";
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process = $this->createMock(Process::class),
                $this->createMock(Process::class),
            ));
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(1));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
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
        $trigger = new CodingStandard(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class),
            $iteration = new Iteration,
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->exactly(2))
            ->method('contains')
            ->withConsecutive(
                [new Name('.php_cs.dist')],
                [new Name('.php_cs.dist')],
            )
            ->will($this->onConsecutiveCalls(true, true));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'" &&
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
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
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
