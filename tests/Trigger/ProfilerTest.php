<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Profiler,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\Processes;
use Innmind\CLI\Environment;
use Innmind\Filesystem\{
    Adapter,
    File,
    Directory,
    Stream\StringStream,
};
use Innmind\Url\Path;
use PHPUnit\Framework\TestCase;

class ProfilerTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Profiler(
                $this->createMock(Filesystem::class),
                $this->createMock(Processes::class)
            )
        );
    }

    public function testDoesntStartProfilerWhenNotStartActivity()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $filesystem
            ->expects($this->never())
            ->method('mount');
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $this->createMock(Environment::class)
        ));
    }

    public function testDoesntStartProfilerWhenNoConfigFolder()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(false);
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntStartProfilerWhenConfigFileIsNotAFolder()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($this->createMock(File::class));
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntStartProfilerWhenConfigFolderDoesntContainEnvFile()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('has')
            ->with('.env')
            ->willReturn(false);
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntStartProfilerWhenDebugModeNotExpressed()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('has')
            ->with('.env')
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with('.env')
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(new StringStream(''));
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntStartProfilerWhenDebugModeNotEnabled()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('has')
            ->with('.env')
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with('.env')
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(new StringStream('DEBUG=0'));
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntStartProfilerWhenNoProfilerUrlProvided()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('has')
            ->with('.env')
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with('.env')
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(new StringStream('DEBUG=1'));
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testStartProfiler()
    {
        $trigger = new Profiler(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class)
        );
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('workingDirectory')
            ->willReturn(new Path('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('has')
            ->with('.env')
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with('.env')
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(new StringStream("DEBUG=1\nPROFILER=http://localhost:8080/"));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "php '-S' 'localhost:8080'" &&
                    $command->workingDirectory() === '/path/to/project/vendor/package/../profiler/public';
            }));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }
}
