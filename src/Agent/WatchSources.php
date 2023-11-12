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
use Innmind\Immutable\Either;

final class WatchSources implements Agent
{
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?Agent {
        $src = $project->resolve(Path::of('src/'));

        $os->filesystem()->watch($src)(
            $activities,
            static fn(Activities $activities) => Either::right( // right in order to have an infinite loop
                $activities->push(Activity::sourcesModified),
            ),
        );

        return $this;
    }
}
