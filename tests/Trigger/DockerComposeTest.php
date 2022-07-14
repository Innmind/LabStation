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
use Innmind\Server\Control\Server\Processes;
use Innmind\CLI\Environment;
use Innmind\Filesystem\{
    Adapter,
    Name,
};
use Innmind\Url\Path;
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

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified, []),
            $this->createMock(Environment::class),
        ));
    }

    public function testDoesntStartDockerComposeWhenNoConfigFile()
    {
        $trigger = new DockerCompose(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
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
            ->with(new Name('docker-compose.yml'))
            ->willReturn(false);
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start, []),
            $env,
        ));
    }

    public function testStartDockerCompose()
    {
        $trigger = new DockerCompose(
            $filesystem = $this->createMock(Filesystem::class),
            $processes = $this->createMock(Processes::class),
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
            ->with(new Name('docker-compose.yml'))
            ->willReturn(true);
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "docker-compose 'up' '-d'" &&
                    $command->workingDirectory()->toString() === '/path/to/project/vendor/package';
            }));

        $this->assertNull($trigger(
            new Activity(Type::start, []),
            $env,
        ));
    }
}
