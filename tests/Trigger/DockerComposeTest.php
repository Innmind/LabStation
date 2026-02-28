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
    Config,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Filesystem\{
    Adapter,
    File,
    File\Content,
};
use Innmind\Immutable\{
    Set,
    Attempt,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

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

        $os = OperatingSystem::new();
        $console = Console::of(
            Environment::inMemory(
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
            Activity::sourcesModified,
            Set::of(Triggers::dockerCompose),
        ));
    }

    public function testDoesntStartDockerComposeWhenNoConfigFile()
    {
        $trigger = new DockerCompose;

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result(Adapter::inMemory()))
                ->useServerControl(Server::via(
                    static fn() => Attempt::error(new \Exception),
                )),
        );
        $console = Console::of(
            Environment::inMemory(
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

        $os = OperatingSystem::new();
        $console = Console::of(
            Environment::inMemory(
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
            $console
                ->environment()
                ->outputted()
                ->toList(),
        );
    }

    public function testStartDockerCompose()
    {
        $trigger = new DockerCompose;
        $project = Adapter::inMemory();
        $_ = $project->add(File::named(
            'docker-compose.yml',
            Content::none(),
        ))->unwrap();

        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($project))
                ->useServerControl(Server::via(
                    function($command) {
                        $this->assertSame(
                            "docker-compose 'up' '-d'",
                            $command->toString(),
                        );
                        $this->assertSame(
                            '/path/to/project/vendor/package/',
                            $command->workingDirectory()->match(
                                static fn($path) => $path->toString(),
                                static fn() => null,
                            ),
                        );

                        return Attempt::result(
                            Builder::foreground(2)->build(),
                        );
                    },
                )),
        );

        $console = Console::of(
            Environment::inMemory(
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
