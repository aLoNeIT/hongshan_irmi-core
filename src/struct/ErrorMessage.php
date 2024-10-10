<?php

declare(strict_types=1);

namespace hongshanhealth\irmi\struct;

class ErrorMessage extends Base
{
    public ?int $code = null;
    public ?string $message = null;
    public ?string $data = null;
}
