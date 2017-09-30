<?php

namespace Darkages;

class PacketBuilder
{
    private $buffer = "";

    public function __construct()
    {
    }

    public function append(string $buffer)
    {
        $this->buffer .= $buffer;
    }

    public function extract(&$result): bool
    {
        $bufferLength = strlen($this->buffer);

        if ($bufferLength < 3) {
            return false;
        }

        if (ord($this->buffer[0]) != STREAM_HEADER) {
            throw new \Exception('Invalid stream header');
        }

        $dataLength = (ord($this->buffer[1]) << 8) | ord($this->buffer[2]);
        $fullLength = $dataLength + 3;

        if ($fullLength > $bufferLength) {
            return false;
        }

        $result = substr($this->buffer, 3, $dataLength);
        $this->buffer = substr($this->buffer, $fullLength);

        return true;
    }
}
