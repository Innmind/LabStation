<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\DockerCompose,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\{
    Processes,
    Process,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Filesystem\{
    Adapter,
    Name,
    File\File,
    File\Content,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class DockerComposeTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new DockerCompose(
                $this->createMock(Filesystem::class),
                $this->createMock(Processes::class),
            ),
        );
    }

    public function testDoesntStartDockerComposeWhenNotStartActivity()
    {
        $trigger = new DockerCompose(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
        );
        $filesystem
            ->expects($this->never())
            ->method('mount');
        $processes
            ->expects($this->never())
            ->method('execute');
        $console = Console::of(
            $this->createMock(Environment::class),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            new Activity(Type::sourcesModified),
            $console,
        ));
    }

    public function testDoesntStartDockerComposeWhenNoConfigFile()
    {
        $trigger = new DockerCompose(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package/'))
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
                '/path/to/project/vendor/package',
            ),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            new Activity(Type::start),
            $console,
        ));
    }

    public function testStartDockerCompose()
    {
        $trigger = new DockerCompose(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
        );
        $project = Adapter\InMemory::new();
        $project->add(File::named(
            'docker-compose.yml',
            Content\None::of(),
        ));
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package/'))
            ->willReturn($project);
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "docker-compose 'up' '-d'" &&
                    '/path/to/project/vendor/package/' === $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    );
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $console = Console::of(
            Environment\InMemory::of(
                [],
                true,
                [],
                [],
                '/path/to/project/vendor/package',
            ),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            new Activity(Type::start),
            $console,
        ));
    }
}
