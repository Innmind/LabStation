<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use function Innmind\LabStation\bootstrap;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\Commands;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testInvokation()
    {
        $os = $this->createMock(OperatingSystem::class);

        $this->assertInstanceOf(Commands::class, bootstrap($os));
    }
}
