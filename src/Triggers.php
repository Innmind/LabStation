<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

/**
 * @psalm-immutable
 */
enum Triggers
{
    case codingStandard;
    case composerUpdate;
    case dockerCompose;
    case psalm;
    case tests;

    /**
     * @psalm-pure
     */
    public static function of(string $value): self
    {
        return match ($value) {
            'cs', 'codingStandard' => self::codingStandard,
            'composer', 'composerUpdate' => self::composerUpdate,
            'docker', 'dockerCompose' => self::dockerCompose,
            'psalm' => self::psalm,
            'tests', 'phpunit' => self::tests,
        };
    }

    /**
     * @psalm-pure
     */
    public static function allow(string $value): bool
    {
        return match ($value) {
            'cs', 'codingStandard' => true,
            'composer', 'composerUpdate' => true,
            'docker', 'dockerCompose' => true,
            'psalm' => true,
            'tests', 'phpunit' => true,
            default => false,
        };
    }
}
