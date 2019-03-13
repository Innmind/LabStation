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
use Innmind\Filesystem\MediaType\MediaType;
use Innmind\Json\Json as Format;
use Innmind\Immutable\Str;

final class Json implements Protocol
{
    public function encode(Activity $activity): Message
    {
        return new Message\Generic(
            new MediaType('application', 'json'),
            Str::of(Format::encode([
                'type' => (string) $activity->type(),
                'data' => $activity->data(),
            ]))
        );
    }

    public function decode(Message $message): Activity
    {
        if ((string) $message->mediaType() !== 'application/json') {
            throw new UnknownMessage((string) $message->content());
        }

        $content = Format::decode((string) $message->content());

        return new Activity(
            Type::{$content['type']}(),
            $content['data']
        );
    }
}