<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\LabStation\Activity\Type;

final class Activity
{
    private Type $type;
    private array $data;

    public function __construct(Type $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function is(Type $type): bool
    {
        return $this->type->equals($type);
    }

    public function type(): Type
    {
        return $this->type;
    }

    public function data(): array
    {
        return $this->data;
    }
}
