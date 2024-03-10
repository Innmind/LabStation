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
            static fn(Activities $activities, $continuation) => $continuation->continue(
                $activities->push(Activity::proofsModified),
            ),
        );

        return $this;
    }
}
