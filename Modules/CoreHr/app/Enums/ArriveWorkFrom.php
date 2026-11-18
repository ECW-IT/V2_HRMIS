<?php

namespace Modules\CoreHr\Enums;

enum ArriveWorkFrom: string
{
    case HOME = 'HOME';
    case BOARDING = 'BOARDING';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Home',
            self::BOARDING => 'Boarding',
        };
    }
}
