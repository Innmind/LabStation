<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\All,
    Trigger,
    Activity,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\Set;
use PHPUnit\Framework\TestCase;

class AllTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Trigger::class, new All);
    }

    public function testTriggerAllSubTriggers()
    {
        $trigger = new All(
            $trigger1 = $this->createMock(Trigger::class),
            $trigger2 = $this->createMock(Trigger::class),
            $trigger3 = $this->createMock(Trigger::class),
        );
        $triggers = Set::of();
        $activity = Activity::start;
        $console = Console::of(
            $this->createMock(Environment::class),
            new Arguments,
            new Options,
        );
        $os = $this->createMock(OperatingSystem::class);
        $trigger1
            ->expects($this->once())
            ->method('__invoke')
            ->with($console, $os, $activity, $triggers)
            ->willReturn($console);
        $trigger2
            ->expects($this->once())
            ->method('__invoke')
            ->with($console, $os, $activity, $triggers)
            ->willReturn($console);
        $trigger3
            ->expects($this->once())
            ->method('__invoke')
            ->with($console, $os, $activity, $triggers)
            ->willReturn($console);

        $this->assertSame($console, $trigger($console, $os, $activity, $triggers));
    }
}
