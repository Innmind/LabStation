<?php

declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\Server\Control\Server\Command;

final class Say
{
    /**
     * @psalm-pure
     */
    public static function of(string $text): Command
    {
        return Command::foreground('say')
            ->withShortOption('v', 'Daniel')
            ->withArgument($text);
    }
}
