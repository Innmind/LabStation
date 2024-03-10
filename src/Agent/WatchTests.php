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

final class WatchTests implements Agent
{
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?Agent {
        $tests = $project->resolve(Path::of('tests/'));
        $filesystem = $os->filesystem();

        if (!$filesystem->contains($tests)) {
            return null;
        }

        $filesystem->watch($tests)(
            $activities,
            static fn(Activities $activities, $continuation) => $continuation->continue(
                $activities->push(Activity::testsModified),
            ),
        );

        return $this;
    }
}
