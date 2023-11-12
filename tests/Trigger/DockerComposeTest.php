<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\DockerCompose,
    Trigger,
    Triggers,
    Activity,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Filesystem,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
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
    File,
    File\Content,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Either,
    SideEffect,
    Set,
};
use PHPUnit\Framework\TestCase;

class DockerComposeTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new DockerCompose,
        );
    }

    public function testDoesntStartDockerComposeWhenNotStartActivity()
    {
        $trigger = new DockerCompose;

        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('filesystem');
        $os
            ->expects($this->never())
            ->method('control');
        $console = Console::of(
            $this->createMock(Environment::class),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::dockerCompose),
        ));
    }

    public function testDoesntStartDockerComposeWhenNoConfigFile()
    {
        $trigger = new DockerCompose;

        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package/'))
            ->willReturn(Adapter\InMemory::new());
        $os
            ->expects($this->never())
            ->method('control');
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
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::dockerCompose),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new DockerCompose;

        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->never())
            ->method('filesystem');
        $os
            ->expects($this->never())
            ->method('control');
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

        $console = $trigger(
            $console,
            $os,
            Activity::start,
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

    public function testStartDockerCompose()
    {
        $trigger = new DockerCompose;

        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $server = $this->createMock(Server::class);
        $processes = $this->createMock(Processes::class);
        $project = Adapter\InMemory::new();
        $project->add(File::named(
            'docker-compose.yml',
            Content::none(),
        ));

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(Path::of('/path/to/project/vendor/package/'))
            ->willReturn($project);
        $os
            ->method('control')
            ->willReturn($server);
        $server
            ->method('processes')
            ->willReturn($processes);
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
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::dockerCompose),
        ));
    }
}
