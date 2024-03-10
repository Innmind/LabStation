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

final class WatchProperties implements Agent
{
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?Agent {
        $properties = $project->resolve(Path::of('properties/'));
        $filesystem = $os->filesystem();

        if (!$filesystem->contains($properties)) {
            return null;
        }

        $filesystem->watch($properties)(
            $activities,
            static fn(Activities $activities, $continuation) => $continuation->continue(
                $activities->push(Activity::propertiesModified),
            ),
        );

        return $this;
    }
}
