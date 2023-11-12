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
    case proofs;

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
            'proofs', 'blackbox', 'bb' => self::proofs,
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
            'proofs', 'blackbox', 'bb' => true,
            default => false,
        };
    }
}
