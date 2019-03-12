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
        return new self('sources.modified');
    }

    public static function testsModified(): self
    {
        return new self('tests.modified');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
