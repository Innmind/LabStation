<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Commands;
use Innmind\OperatingSystem\OperatingSystem;

function bootstrap(OperatingSystem $os): Commands
{
    return Commands::of(
        new Command\Work(
            new Monitor(
                $os,
                $iteration = new Iteration,
                new Trigger\All(
                    new Trigger\DockerCompose,
                    new Trigger\BlackBox($iteration),
                    new Trigger\Tests($iteration),
                    new Trigger\Psalm($iteration),
                    new Trigger\CodingStandard($iteration),
                    new Trigger\ComposerUpdate,
                ),
                new Agent\WatchSources,
                new Agent\WatchTests,
                new Agent\WatchProofs,
                new Agent\WatchFixtures,
                new Agent\WatchProperties,
            ),
        ),
    );
}
