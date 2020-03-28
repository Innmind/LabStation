<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Activity;

use Innmind\LabStation\Activity\Type;
use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Type::class, Type::sourcesModified());
        $this->assertInstanceOf(Type::class, Type::start());
        $this->assertInstanceOf(Type::class, Type::testsModified());
        $this->assertSame('sourcesModified', Type::sourcesModified()->toString());
        $this->assertSame('start', Type::start()->toString());
        $this->assertSame('testsModified', Type::testsModified()->toString());
        $this->assertTrue(Type::sourcesModified()->equals(Type::sourcesModified()));
        $this->assertTrue(Type::start()->equals(Type::start()));
        $this->assertTrue(Type::testsModified()->equals(Type::testsModified()));
        $this->assertFalse(Type::testsModified()->equals(Type::sourcesModified()));
        $this->assertFalse(Type::SourcesModified()->equals(Type::testsModified()));
    }
}
