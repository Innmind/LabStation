<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Psalm,
    Trigger,
    Activity,
    Activity\Type,
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

class PsalmTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Psalm(
                $this->createMock(Processes::class),
                $this->createMock(Filesystem::class)
            )
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $this->createMock(Filesystem::class)
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
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('psalm.xml'))
            ->willReturn(false);
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
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('psalm.xml'))
            ->willReturn(true);
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/psalm" &&
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "say 'Psalm : ok'";
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some output'));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
    }

    public function testTriggerTestsSuiteWhenTestsModified()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('psalm.xml'))
            ->willReturn(true);
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/psalm" &&
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "say 'Psalm : ok'";
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some output'));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $this->assertNull($trigger(
            new Activity(Type::testsModified(), []),
            $env
        ));
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('psalm.xml'))
            ->willReturn(true);
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/psalm" &&
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
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(1));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "say 'Psalm : failing'";
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn($workingDirectory);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Psalm(
            $processes = $this->createMock(Processes::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $workingDirectory = Path::of('/somewhere');
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with($workingDirectory)
            ->willReturn($directory = $this->createMock(Adapter::class));
        $directory
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('psalm.xml'))
            ->willReturn(true);
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "vendor/bin/psalm" &&
                    $command->workingDirectory()->toString() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
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
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::of('string', '--silent'));

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
    }
}
