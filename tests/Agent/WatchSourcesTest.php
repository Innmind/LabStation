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
use Innmind\Filesystem\Adapter;
use Innmind\FileWatch\Watch;
use Innmind\Url\Path;
use Innmind\Immutable\Set;
use PHPUnit\Framework\TestCase;

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

        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result(Adapter::inMemory()))
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
                Activity::sourcesModified,
            ],
            $activities->toList(),
        );
    }
}
