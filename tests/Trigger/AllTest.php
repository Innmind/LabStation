<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\All,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
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
            $trigger3 = $this->createMock(Trigger::class)
        );
        $activity = new Activity(Type::start(), []);
        $env = $this->createMock(Environment::class);
        $trigger1
            ->expects($this->once())
            ->method('__invoke')
            ->with($activity, $env);
        $trigger2
            ->expects($this->once())
            ->method('__invoke')
            ->with($activity, $env);
        $trigger3
            ->expects($this->once())
            ->method('__invoke')
            ->with($activity, $env);

        $this->assertNull($trigger($activity, $env));
    }
}
