<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchProofs,
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
use Innmind\Filesystem\{
    Adapter,
    Directory,
};
use Innmind\FileWatch\Watch;
use Innmind\Url\Path;
use Innmind\Immutable\Set;
use PHPUnit\Framework\TestCase;

class WatchProofsTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchProofs,
        );
    }

    public function testSendMessageWhenProofsAreModified()
    {
        $agent = new WatchProofs;

        $adapter = Adapter::inMemory();
        $_ = $adapter
            ->add(Directory::named('proofs'))
            ->unwrap();

        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useFileWatch(Watch::via()), // todo simulate file change
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
                Activity::proofsModified,
            ],
            $activities->toList(),
        );
    }

    public function testDoesntWatchWhenTheFolderDoesntExist()
    {
        $agent = new WatchProofs;

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
