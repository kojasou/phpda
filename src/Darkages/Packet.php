<?php

namespace Darkages;

class Packet implements \ArrayAccess
{
    private $data;
    private $position = 0;

    public function __construct($data = "")
    {
        $this->data = $data;
    }

    public function getOpcode(): int
    {
        return ord($this->data[0]) & 0xFF;
    }

    public function getLength(): int
    {
        return strlen($this->data);
    }

    public function setPosition(int $position): int
    {
        if ($position < 0) {
            $position = 0;
        }

        $length = $this->getLength();

        if ($position > $length) {
            $position = $length;
        }

        return $this->position = $position;
    }

    public function writeByte(int $value)
    {
        $this->writeString(chr($value & 0xFF));
    }

    public function writeInt16(int $value)
    {
        $this->writeString(chr(($value >> 8) & 0xFF));
        $this->writeString(chr($value & 0xFF));
    }

    public function writeInt32(int $value)
    {
        $this->writeString(chr(($value >> 24) & 0xFF));
        $this->writeString(chr(($value >> 16) & 0xFF));
        $this->writeString(chr(($value >> 8) & 0xFF));
        $this->writeString(chr($value & 0xFF));
    }

    public function writeString(string $value, int $lengthType = STR_LENGTH_NONE)
    {
        $length = strlen($value);
        if ($lengthType == STR_LENGTH_8) {
            $this->writeByte($length);
        } elseif ($lengthType == STR_LENGTH_16) {
            $this->writeInt16($length);
        }
        $this->data = substr_replace($this->data, $value, $this->position, $length);
        $this->position += $length;
    }

    public function readByte(): int
    {
        return ord($this->data[$this->position++]);
    }

    public function readInt16(): int
    {
        return ($this->readByte() << 8) | $this->readByte();
    }

    public function readInt32(): int
    {
        return ($this->readInt16() << 16) | $this->readInt16();
    }

    public function readString(int $length): string
    {
        $result = substr($this->data, $this->position, $length);
        $this->position += strlen($result);
        return $result;
    }

    public function toString(): string
    {
        return $this->data;
    }

    public function getHexString(): string
    {
        return chunk_split(bin2hex($this->data), 2, ' ');
    }

    public function getAsciiString(): string
    {
        $ascii = '';
        $length = strlen($this->data);
        for ($i = 0; $i < $length; ++$i) {
            $n = ord($this->data[$i]);
            if ($n < 32 || $n > 126) {
                $ascii .= '.';
            } else {
                $ascii .= $this->data[$i];
            }
        }
        return $ascii;
    }

    public function offsetSet($offset, $value)
    {
        if (is_int($value)) {
            $chr = chr($value & 0xFF);

            if (is_null($offset)) {
                throw new \Exception('[] operator not supported for Packet');
            }

            if (is_int($offset)) {
                $length = strlen($this->data);

                if ($offset == $length) {
                    $this->data .= $chr;
                } elseif ($offset < $length) {
                    $this->data[$offset] = $chr;
                } elseif ($offset > $length) {
                    throw new \OutOfBoundsException('offset is greater than buffer length');
                }
            }
        }
    }

    public function offsetExists($offset)
    {
        return $offset < strlen($this->data);
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Cannot unset Packet offsets');
    }

    public function offsetGet($offset)
    {
        return ord($this->data[$offset]) & 0xFF;
    }
}
