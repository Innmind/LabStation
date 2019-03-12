<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use Innmind\LabStation\{
    Activity,
    Activity\Type,
};
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase
{
    public function testInterface()
    {
        $activity = new Activity(Type::sourcesModified(), ['foo' => 'bar']);

        $this->assertTrue($activity->is(Type::sourcesModified()));
        $this->assertSame(['foo' => 'bar'], $activity->data());
    }
}
