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
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class AllTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Trigger::class, new All);
    }

    public function testTriggerAllSubTriggers()
    {
        $trigger = new All(
            new class implements Trigger {
                public function __invoke(
                    Console $console,
                    OperatingSystem $os,
                    Activity $activity,
                    Set $triggers,
                ): Console {
                    return $console;
                }
            },
            new class implements Trigger {
                public function __invoke(
                    Console $console,
                    OperatingSystem $os,
                    Activity $activity,
                    Set $triggers,
                ): Console {
                    return $console;
                }
            },
            new class implements Trigger {
                public function __invoke(
                    Console $console,
                    OperatingSystem $os,
                    Activity $activity,
                    Set $triggers,
                ): Console {
                    return $console;
                }
            },
        );
        $triggers = Set::of();
        $activity = Activity::start;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );
        $os = OperatingSystem::new();

        $this->assertSame($console, $trigger($console, $os, $activity, $triggers));
    }
}
