<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent,
    Activities,
    Activity,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Url\Path;

final class WatchFixtures implements Agent
{
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?Agent {
        $fixtures = $project->resolve(Path::of('fixtures/'));
        $filesystem = $os->filesystem();

        if (!$filesystem->contains($fixtures)) {
            return null;
        }

        $filesystem->watch($fixtures)(
            $activities,
            static fn(Activities $activities, $continuation) => $continuation->continue(
                $activities->push(Activity::fixturesModified),
            ),
        );

        return $this;
    }
}
