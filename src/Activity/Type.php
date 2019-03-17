<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Activity;

final class Type
{
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function sourcesModified(): self
    {
        return new self('sourcesModified');
    }

    public static function testsModified(): self
    {
        return new self('testsModified');
    }

    public static function start(): self
    {
        return new self('start');
    }

    public static function gitBranchChanged(): self
    {
        return new self('gitBranchChanged');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
