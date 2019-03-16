<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent,
    Protocol,
    Activity,
    Activity\Type,
};
use Innmind\FileWatch\Watch;
use Innmind\IPC\{
    IPC,
    Process\Name,
};
use Innmind\Url\{
    PathInterface,
    Path,
};
use Innmind\Git\Git;

final class WatchCurrentGitBranch implements Agent
{
    private $git;
    private $protocol;
    private $watch;
    private $ipc;
    private $monitor;

    public function __construct(
        Git $git,
        Protocol $protocol,
        Watch $watch,
        IPC $ipc,
        Name $monitor
    ) {
        $this->git = $git;
        $this->protocol = $protocol;
        $this->watch = $watch;
        $this->ipc = $ipc;
        $this->monitor = $monitor;
    }

    public function __invoke(PathInterface $project): void
    {
        $git = new Path($project.'/.git');
        $repository = $this->git->repository($project);
        $previousBranch = $repository->head();

        ($this->watch)($git)(function() use (&$previousBranch, $repository) {
            $currentBranch = $repository->head();

            if ((string) $currentBranch === (string) $previousBranch) {
                return;
            }

            $previousBranch = $currentBranch;
            $monitor = $this->ipc->get($this->monitor);
            $monitor->send(
                $this->protocol->encode(
                    new Activity(
                        Type::gitBranchChanged(),
                        ['branch' => (string) $currentBranch]
                    )
                )
            );
            $monitor->close();
        });
    }
}
