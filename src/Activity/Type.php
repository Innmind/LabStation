<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Activity;

use Innmind\LabStation\Exception\LogicException;

final class Type
{
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $type): self
    {
        switch ($type) {
            case 'sourcesModified':
                return self::sourcesModified();

            case 'testsModified':
                return self::testsModified();

            case 'start':
                return self::start();
        }

        throw new LogicException("Unknown type '$type'");
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
