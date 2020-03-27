<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Graphs,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
use Innmind\OperatingSystem\{
    Filesystem,
    Sockets,
};
use Innmind\Server\Control\Server\{
    Processes,
    Process,
    Process\Output,
    Process\ExitCode,
};
use Innmind\Url\Path;
use Innmind\Filesystem\{
    Adapter,
    File,
    Name,
};
use Innmind\Stream\{
    Readable,
    Writable,
};
use Innmind\Immutable\{
    Str,
    Sequence,
};
use PHPUnit\Framework\TestCase;

class GraphsTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Graphs(
                $this->createMock(Filesystem::class),
                $this->createMock(Processes::class),
                $this->createMock(Sockets::class),
                Path::none(),
            )
        );
    }

    public function testDoesntTriggerWhenNotStartActivity()
    {
        $trigger = new Graphs(
            $filesystem = $this->createMock(Filesystem::class),
            $this->createMock(Processes::class),
            new Sockets\Unix,
            Path::none(),
        );
        $filesystem
            ->expects($this->never())
            ->method('mount');

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $this->createMock(Environment::class)
        ));
    }

    public function testOpenDependencyGraphsOnStart()
    {
        $trigger = new Graphs(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            new Sockets\Unix,
            Path::of('/tmp/folder')
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('composer.json'))
            ->willReturn($composer = $this->createMock(File::class));
        $composer
            ->expects($this->once())
            ->method('content')
            ->willReturn($content = $this->createMock(Readable::class));
        $content
            ->expects($this->once())
            ->method('toString')
            ->willReturn('{"name":"innmind/lab-station"}');
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'depends-on' 'innmind/lab-station' 'innmind'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
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
            ->method('toString')
            ->willReturn('innmind_lab-station_dependents.svg');
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "open 'innmind_lab-station_dependents.svg'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'of' 'innmind/lab-station'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
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
            ->method('toString')
            ->willReturn('innmind_lab-station.svg');
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "open 'innmind_lab-station.svg'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $processes
            ->expects($this->at(4))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'vendor' 'innmind'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
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
            ->method('toString')
            ->willReturn('innmind.svg');
        $processes
            ->expects($this->at(5))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "open 'innmind.svg'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('Render dependency graphs? [Y/n] '));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntTryToOpenAFailedGraphGenerationAndDoesntPreventGeneratingOthers()
    {
        $trigger = new Graphs(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            new Sockets\Unix,
            Path::of('/tmp/folder')
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/somewhere'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('composer.json'))
            ->willReturn($composer = $this->createMock(File::class));
        $composer
            ->expects($this->once())
            ->method('content')
            ->willReturn($content = $this->createMock(Readable::class));
        $content
            ->expects($this->once())
            ->method('toString')
            ->willReturn('{"name":"innmind/lab-station"}');
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'depends-on' 'innmind/lab-station' 'innmind'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(1));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('failed to generate graph');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('Render dependency graphs? [Y/n] '));
        $env
            ->expects($this->any())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('failed to generate graph'));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'of' 'innmind/lab-station'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
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
            ->method('toString')
            ->willReturn('innmind_lab-station.svg');
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "open 'innmind_lab-station.svg'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "dependency-graph 'vendor' 'innmind'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
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
            ->method('toString')
            ->willReturn('innmind.svg');
        $processes
            ->expects($this->at(4))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "open 'innmind.svg'" &&
                    $command->workingDirectory()->toString() === '/tmp/folder';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntOpenDependencyGraphsWhenAnsweringByTheNegative()
    {
        $trigger = new Graphs(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
            new Sockets\Unix,
            Path::of('/tmp/folder')
        );
        $filesystem
            ->expects($this->never())
            ->method('mount');
        $processes
            ->expects($this->never())
            ->method('execute');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "n\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('Render dependency graphs? [Y/n] '));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }
}
