<?php

namespace Path\Exception;

class IOException extends \Exception
{
    public function __construct(
        string $message = "Read/write error",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
