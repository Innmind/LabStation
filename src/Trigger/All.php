<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
};
use Innmind\CLI\Environment;

final class All implements Trigger
{
    /** @var list<Trigger> */
    private array $triggers;

    public function __construct(Trigger ...$triggers)
    {
        $this->triggers = $triggers;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        foreach ($this->triggers as $trigger) {
            $trigger($activity, $env);
        }
    }
}
