<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\IPC\Message;

interface Protocol
{
    public function encode(Activity $activity): Message;
    public function decode(Message $essage): Activity;
}
