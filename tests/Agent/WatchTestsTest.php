<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchTests,
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

class WatchTestsTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchTests,
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchTests;

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
            ->with(Path::of('/vendor/package/tests/'))
            ->willReturn(true);
        $filesystem
            ->expects($this->once())
            ->method('watch')
            ->with(Path::of('/vendor/package/tests/'))
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
                Activity::testsModified,
            ],
            $activities->toList(),
        );
    }

    public function testDoesntWatchWhenTheFolderDoesntExist()
    {
        $agent = new WatchTests;

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
            ->with(Path::of('/vendor/package/tests/'))
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
