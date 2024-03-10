<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchProperties,
    Agent,
    Activities,
    Activity,
    Trigger,
    Triggers,
    Iteration,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Filesystem,
};
use Innmind\FileWatch\{
    Ping,
    Continuation,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Maybe,
    Set,
};
use PHPUnit\Framework\TestCase;

class WatchPropertiesTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchProperties,
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchProperties;

        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $activities = Activities::new(
            $this->createMock(Trigger::class),
            new Iteration,
            Set::of(...Triggers::cases()),
        );
        $project = Path::of('/vendor/package/');

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('contains')
            ->with(Path::of('/vendor/package/properties/'))
            ->willReturn(true);
        $filesystem
            ->expects($this->once())
            ->method('watch')
            ->with(Path::of('/vendor/package/properties/'))
            ->willReturn($ping = $this->createMock(Ping::class));
        $ping
            ->expects($this->once())
            ->method('__invoke')
            ->with($activities, $this->callback(static function($listen) use ($activities): bool {
                $listen($activities, Continuation::of($activities)); // simulate folder modification

                return true;
            }))
            ->willReturn(Maybe::just($activities));

        $this->assertSame($agent, $agent($os, $project, $activities));
        $this->assertEquals(
            [
                Activity::start,
                Activity::propertiesModified,
            ],
            $activities->toList(),
        );
    }

    public function testDoesntWatchWhenTheFolderDoesntExist()
    {
        $agent = new WatchProperties;

        $os = $this->createMock(OperatingSystem::class);
        $filesystem = $this->createMock(Filesystem::class);
        $activities = Activities::new(
            $this->createMock(Trigger::class),
            new Iteration,
            Set::of(...Triggers::cases()),
        );
        $project = Path::of('/vendor/package/');

        $os
            ->method('filesystem')
            ->willReturn($filesystem);
        $filesystem
            ->expects($this->once())
            ->method('contains')
            ->with(Path::of('/vendor/package/properties/'))
            ->willReturn(false);
        $filesystem
            ->expects($this->never())
            ->method('watch');

        $this->assertNull($agent($os, $project, $activities));
        $this->assertEquals(
            [Activity::start],
            $activities->toList(),
        );
    }
}
