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

final class WatchProofs implements Agent
{
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?Agent {
        $proofs = $project->resolve(Path::of('proofs/'));
        $filesystem = $os->filesystem();

        if (!$filesystem->contains($proofs)) {
            return null;
        }

        $filesystem->watch($proofs)(
            $activities,
            static fn(Activities $activities) => Either::right( // right in order to have an infinite loop
                $activities->push(Activity::proofsModified),
            ),
        );

        return $this;
    }
}
