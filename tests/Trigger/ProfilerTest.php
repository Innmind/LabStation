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
    Name,
};
use Innmind\Stream\Readable\Stream;
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('.env'))
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('.env'))
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with(new Name('.env'))
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(Stream::ofContent(''));
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('.env'))
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with(new Name('.env'))
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(Stream::ofContent('DEBUG=0'));
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('.env'))
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with(new Name('.env'))
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(Stream::ofContent('DEBUG=1'));
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
            ->willReturn(Path::of('/path/to/project/vendor/package'));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package'))
            ->willReturn($project = $this->createMock(Adapter::class));
        $project
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('config'))
            ->willReturn(true);
        $project
            ->expects($this->once())
            ->method('get')
            ->with(new Name('config'))
            ->willReturn($config = $this->createMock(Directory::class));
        $config
            ->expects($this->once())
            ->method('contains')
            ->with(new Name('.env'))
            ->willReturn(true);
        $config
            ->expects($this->once())
            ->method('get')
            ->with(new Name('.env'))
            ->willReturn($file = $this->createMock(File::class));
        $file
            ->expects($this->once())
            ->method('content')
            ->willReturn(Stream::ofContent("DEBUG=1\nPROFILER=http://localhost:8080/"));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "php '-S' 'localhost:8080'" &&
                    $command->workingDirectory()->toString() === '/path/to/project/vendor/package/../profiler/public';
            }));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }
}
