<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchFixtures,
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
use Innmind\Filesystem\{
    Adapter,
    Directory,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Set,
    Attempt,
};
use PHPUnit\Framework\TestCase;

class WatchFixturesTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchFixtures,
        );
    }

    public function testSendMessageWhenFixturesAreModified()
    {
        $agent = new WatchFixtures;

        $adapter = Adapter::inMemory();
        $_ = $adapter
            ->add(Directory::named('fixtures'))
            ->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    function($command) use (&$count) {
                        $this->assertSame(
                            "find '/vendor/package/fixtures/' '-type' 'f' | xargs '-n' '1' '-r' 'stat' '-f' '%Sm %N' '-t' '%Y-%m-%dT%H-%M-%S'",
                            $command->toString(),
                        );

                        $builder = Builder::foreground(2);
                        $builder = match ($count) {
                            0 => $builder->success([['output', 'output']]),
                            1 => $builder->success([['changed', 'output']]),
                            2 => $builder->failed(),
                        };
                        ++$count;

                        return Attempt::result($builder->build());
                    },
                )),
        );

        $activities = Activities::new(
            $this->createMock(Trigger::class),
            new Iteration,
            Set::of(...Triggers::cases()),
        );
        $project = Path::of('/vendor/package/');

        $this->assertSame($agent, $agent($os, $project, $activities));
        $this->assertEquals(
            [
                Activity::start,
                Activity::fixturesModified,
            ],
            $activities->toList(),
        );
    }

    public function testDoesntWatchWhenTheFolderDoesntExist()
    {
        $agent = new WatchFixtures;

        $os = OperatingSystem::new();
        $activities = Activities::new(
            $this->createMock(Trigger::class),
            new Iteration,
            Set::of(...Triggers::cases()),
        );
        $project = Path::of('/vendor/package/');

        $this->assertNull($agent($os, $project, $activities));
        $this->assertEquals(
            [Activity::start],
            $activities->toList(),
        );
    }
}
