<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\LabStation\Activity\Type;

final class Activity
{
    private Type $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    public function type(): Type
    {
        return $this->type;
    }
}
