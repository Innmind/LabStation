<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Protocol;

use Innmind\LabStation\{
    Protocol,
    Activity,
    Activity\Type,
    Exception\UnknownMessage,
};
use Innmind\IPC\Message;
use Innmind\MediaType\MediaType;
use Innmind\Json\Json as Format;
use Innmind\Immutable\Str;

final class Json implements Protocol
{
    public function encode(Activity $activity): Message
    {
        return new Message\Generic(
            new MediaType('application', 'json'),
            Str::of(Format::encode([
                'type' => $activity->type()->toString(),
                'data' => $activity->data(),
            ]))
        );
    }

    public function decode(Message $message): Activity
    {
        if ($message->mediaType()->toString() !== 'application/json') {
            throw new UnknownMessage($message->content()->toString());
        }

        /** @var array{type: string, data: array} */
        $content = Format::decode($message->content()->toString());

        return new Activity(
            Type::of($content['type']),
            $content['data']
        );
    }
}
