<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Protocol;

use Innmind\LabStation\{
    Protocol\Json,
    Protocol,
    Activity,
    Activity\Type,
    Exception\UnknownMessage,
};
use Innmind\IPC\Message;
use Innmind\MediaType\MediaType;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Protocol::class, new Json);
    }

    public function testEncode()
    {
        $json = new Json;

        $message = $json->encode(new Activity(Type::sourcesModified(), ['foo' => 'bar']));

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('application/json', $message->mediaType()->toString());
        $this->assertSame(
            '{"type":"sourcesModified","data":{"foo":"bar"}}',
            $message->content()->toString(),
        );
    }

    public function testThrowWhenUnknownMessageMediaType()
    {
        $this->expectException(UnknownMessage::class);
        $this->expectExceptionMessage('foobar');

        (new Json)->decode(new Message\Generic(
            new MediaType('text', 'plain'),
            Str::of('foobar'),
        ));
    }

    public function testDecode()
    {
        $activity = (new Json)->decode(new Message\Generic(
            new MediaType('application', 'json'),
            Str::of('{"type":"sourcesModified","data":{"foo":"bar"}}'),
        ));

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertTrue($activity->is(Type::sourcesModified()));
        $this->assertSame(['foo' => 'bar'], $activity->data());
    }
}
