<?php

namespace Darkages;

class PacketCryptoProvider
{
    private const MINIMUM_SEED = 0;
    private const MAXIMUM_SEED = 9;
    private const DEFAULT_SEED = 0;
    private const SALT_LENGTH = 256;
    private const KEYSTREAM_LENGTH = 9;
    private const DEFAULT_KEYSTREAM = 'UrkcnItnI';
    private const KEYSTREAM2_TABLE_LENGTH = 1024;

    private $seed;
    private $salt;
    private $keystream1;
    private $keystream2;
    private $keystream2Table;
    private $encryptSequence = 0;

    public function __construct(int $seed = self::DEFAULT_SEED, string $keystream = self::DEFAULT_KEYSTREAM)
    {
        $this->setParams($seed, $keystream);
        if ($keystream == self::DEFAULT_KEYSTREAM) {
            $this->keystream1[3] = chr(0xE5);
            $this->keystream1[7] = chr(0xA3);
        }
        $this->keystream2Table = str_repeat(chr(0), self::KEYSTREAM2_TABLE_LENGTH);
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function getKeystream1(): string
    {
        return $this->keystream1;
    }

    public function getKeystream2(): string
    {
        return $this->keystream2;
    }

    public function setParams(int $seed, string $keystream)
    {
        if ($seed < self::MINIMUM_SEED || $seed > self::MAXIMUM_SEED) {
            throw new \Exception('seed must be a value between '.self::MINIMUM_SEED.' and '.self::MAXIMUM_SEED);
        }

        if ($keystream == null) {
            throw new \Exception('keystream cannot be null');
        }

        if (strlen($keystream) != self::KEYSTREAM_LENGTH) {
            throw new \Exception('keystream must be '.self::KEYSTREAM_LENGTH.' characters long');
        }

        $this->seed = $seed;
        $this->keystream1 = $keystream;
        $this->generateSalt();
    }

    public function resetEncryptSequence()
    {
        $this->encryptSequence = 0;
    }

    public function encrypt(string $data, int $offset, int $count, bool $useKeystream2): string
    {
        $encryptedData = $data[$offset];

        $sequence = $this->encryptSequence++;
        $encryptedData .= chr($sequence);

        $encryptedData .= substr($data, $offset + 1, $count - 1);
        $encryptedData .= chr(0);

        if ($useKeystream2) {
            $encryptedData .= $data[$offset];
        }

        $keystream2Seed = rand();
        $a = ((($keystream2Seed & 0xFFFF) % 65277) + 256) & 0xFFFF;
        $b = (((($keystream2Seed & 0xFF0000) >> 16) % 155) + 100) & 0xFF;

        $this->generateKeystream2($a, $b);

        $this->transform($encryptedData, 2, $count - 1,
            $useKeystream2 ? $this->keystream2 : $this->keystream1, $sequence);

        $hash = hex2bin(md5($encryptedData));

        $encryptedData .= $hash[13];
        $encryptedData .= $hash[3];
        $encryptedData .= $hash[11];
        $encryptedData .= $hash[7];

        $a ^= 0x7470;
        $b ^= 0x23;

        $encryptedData .= chr($a & 0xFF);
        $encryptedData .= chr($b & 0xFF);
        $encryptedData .= chr(($a >> 8) & 0xFF);

        return $encryptedData;
    }

    public function decrypt(string $buffer, int $offset, int $count, bool $useKeystream2): string
    {
        $pos = $count - 3;

        $a = ord($buffer[$pos + 2]) << 8 | ord($buffer[$pos]);
        $b = ord($buffer[$pos + 1]);

        $a ^= 0x6474;
        $b ^= 0x24;

        $decryptedBytes = $buffer[$offset];
        $decryptedBytes .= substr($buffer, 2, $count - 5);

        $this->generateKeystream2($a, $b);

        $this->transform($decryptedBytes, 1, $count - 5,
            $useKeystream2 ? $this->keystream2 : $this->keystream1, ord($buffer[$offset + 1]));

        return $decryptedBytes;
    }

    public function generateKeystream2Table(string $name)
    {
        $keystreamTable = md5(md5($name));
        for ($i = 0; $i < 31; ++$i) {
            $keystreamTable .= md5($keystreamTable);
        }
        $this->keystream2Table = $keystreamTable;
    }

    private function generateSalt()
    {
        $saltByte = 0;

        for ($i = 0; $i < self::SALT_LENGTH; ++$i)
        {
            switch ($this->seed) {
                case 0:
                    $saltByte = $i;
                    break;
                case 1:
                    $saltByte = ($i % 2 != 0 ? -1 : 1) * (($i + 1) / 2) + 128;
                    break;
                case 2:
                    $saltByte = 255 - $i;
                    break;
                case 3:
                    $saltByte = ($i % 2 != 0 ? -1 : 1) * ((255 - $i) / 2) + 128;
                    break;
                case 4:
                    $saltByte = $i / 16 * ($i / 16);
                    break;
                case 5:
                    $saltByte = 2 * $i % 256;
                    break;
                case 6:
                    $saltByte = 255 - 2 * $i % 256;
                    break;
                case 7:
                    if ($i > 127) {
                        $saltByte = 2 * $i - 256;
                    } else {
                        $saltByte = 255 - 2 * $i;
                    }
                    break;
                case 8:
                    if ($i > 127) {
                        $saltByte = 511 - 2 * $i;
                    } else {
                        $saltByte = 2 * $i;
                    }
                    break;
                case 9:
                    $saltByte = 255 - ($i - 128) / 8 * (($i - 128) / 8) % 256;
                    break;
            }

            $saltByte |= ($saltByte << 8) | (($saltByte | ($saltByte << 8)) << 16);
            $this->salt[$i] = $saltByte & 0xFF;
        }
    }

    private function generateKeystream2(int $a, int $b)
    {
        $keystream = "";
        for ($i = 0; $i < self::KEYSTREAM_LENGTH; ++$i) {
            $keystream .= $this->keystream2Table[($i * (self::KEYSTREAM_LENGTH * $i + $b * $b) + $a) % self::KEYSTREAM2_TABLE_LENGTH];
        }
        $this->keystream2 = $keystream;
    }

    public function transform(string &$buffer, int $offset, int $count, string $keystream, int $sequence)
    {
        for ($i = 0; $i < $count; ++$i) {
            $buffer[$offset + $i] = chr((ord($buffer[$offset + $i]) ^ $this->salt[$sequence]) & 0xFF);
            $buffer[$offset + $i] = chr((ord($buffer[$offset + $i]) ^ ord($keystream[$i % self::KEYSTREAM_LENGTH])) & 0xFF);
            $saltIndex = ($i / self::KEYSTREAM_LENGTH) % self::SALT_LENGTH;

            if ($saltIndex != $sequence) {
                $buffer[$offset + $i] = chr((ord($buffer[$offset + $i]) ^ $this->salt[$saltIndex]) & 0xFF);
            }
        }
    }
}
