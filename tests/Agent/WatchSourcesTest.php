<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchSources,
    Agent,
    Activities,
    Activity,
    Trigger,
    Triggers,
    Iteration,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Config,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
};
use Innmind\Time\Halt;
use Innmind\Filesystem\{
    Adapter,
    Directory,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Set,
    Attempt,
    SideEffect,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class WatchSourcesTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchSources,
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchSources;

        $adapter = Adapter::inMemory();
        $_ = $adapter
            ->add(Directory::named('src'))
            ->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    static function($command) use (&$count) {
                        $builder = Builder::foreground(2);
                        $builder = match ($count) {
                            0 => $builder->success([['output', 'output']]),
                            1 => $builder->success([['changed', 'output']]),
                            2 => $builder->failed(),
                        };
                        ++$count;

                        return Attempt::result($builder->build());
                    },
                ))
                ->haltProcessVia(Halt::via(
                    static fn() => Attempt::result(SideEffect::identity),
                )),
        );

        $activities = Activities::new(
            new Trigger\All,
            new Iteration,
            Set::of(...Triggers::cases()),
        );
        $project = Path::of('/vendor/package/');

        $this->assertSame($agent, $agent($os, $project, $activities));
        $this->assertEquals(
            [
                Activity::start,
                Activity::sourcesModified,
            ],
            $activities->toList(),
        );
    }
}
