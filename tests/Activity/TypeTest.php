<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Activity;

use Innmind\LabStation\{
    Activity\Type,
    Exception\LogicException,
};
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class TypeTest extends TestCase
{
    use BlackBox;

    public function testInterface()
    {
        $this
            ->forAll($this->names())
            ->then(function($name) {
                $this->assertInstanceOf(Type::class, Type::$name());
            });
    }

    public function testOf()
    {
        $this
            ->forAll($this->names())
            ->then(function($name) {
                $this->assertTrue(Type::$name()->equals(Type::of($name)));
            });
    }

    public function testUnknownNameThrows()
    {
        $this
            ->forAll(Set\Strings::any())
            ->then(function($unknown) {
                $this->expectException(LogicException::class);

                Type::of($unknown);
            });
    }

    public function testTypeStringResolveToFunctioName()
    {
        $this
            ->forAll($this->names())
            ->then(function($name) {
                $this->assertSame($name, Type::$name()->toString());
            });
    }

    public function testEquality()
    {
        $this
            ->forAll($this->names())
            ->then(function($name) {
                $this->assertTrue(Type::$name()->equals(Type::$name()));
            });
    }

    public function testInequality()
    {
        $this
            ->forAll(
                $this->names(),
                $this->names(),
            )
            ->filter(fn($a, $b) => $a !== $b)
            ->then(function($a, $b) {
                $this->assertFalse(Type::$a()->equals(Type::$b()));
            });
    }

    private function names(): Set
    {
        return Set\Elements::of(
            'sourcesModified',
            'start',
            'testsModified',
            'fixturesModified',
            'propertiesModified',
        );
    }
}
