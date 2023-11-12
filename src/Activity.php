<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

enum Activity
{
    case sourcesModified;
    case testsModified;
    case fixturesModified;
    case propertiesModified;
    case start;

    public static function of(string $type): self
    {
        return match ($type) {
            'sourcesModified' => self::sourcesModified,
            'testsModified' => self::testsModified,
            'fixturesModified' => self::fixturesModified,
            'propertiesModified' => self::propertiesModified,
            'start' => self::start,
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::sourcesModified => 'sourcesModified',
            self::testsModified => 'testsModified',
            self::fixturesModified => 'fixturesModified',
            self::propertiesModified => 'propertiesModified',
            self::start => 'start',
        };
    }
}
