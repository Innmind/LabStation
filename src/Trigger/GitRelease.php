<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\{
    Environment,
    Question\Question,
    Question\ChoiceQuestion,
};
use Innmind\Git\{
    Git,
    Message,
    Exception\DomainException,
};
use Innmind\GitRelease\{
    Release,
    LatestVersion,
};
use Innmind\Immutable\{
    Map,
    Str,
};

final class GitRelease implements Trigger
{
    private $git;
    private $release;
    private $latestVersion;

    public function __construct(Git $git, Release $release, LatestVersion $latestVersion)
    {
        $this->git = $git;
        $this->release = $release;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::gitBranchChanged())) {
            return;
        }

        if ($activity->data()['branch'] !== 'master') {
            return;
        }

        $repository = $this->git->repository($env->workingDirectory());
        $version = ($this->latestVersion)($repository);

        $env->output()->write(Str::of("$version\n"));

        $ask = new ChoiceQuestion(
            'Kind of release:',
            Map::of('scalar', 'scalar')
                (1, 'major')
                (2, 'minor')
                (3, 'bugfix')
                (4, 'none')
        );
        $response = $ask($env->input(), $env->output());

        if ($response->empty()) {
            return;
        }

        switch ($response->current()) {
            case 'major':
                $version = $version->increaseMajor();
                break;

            case 'minor':
                $version = $version->increaseMinor();
                break;

            case 'bugfix':
                $version = $version->increaseBugfix();
                break;

            default:
                return;
        }

        $message = (new Question('message:'))($env->input(), $env->output());

        try {
            $message = new Message((string) $message);
        } catch (DomainException $e) {
            $env->error()->write(Str::of("Invalid message\n"));

            return;
        }

        ($this->release)($repository, $version, $message);
    }
}
