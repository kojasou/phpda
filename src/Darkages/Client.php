<?php

namespace Darkages;

class Client
{
    private const READ_LENGTH = 4096;

    private $socket;
    private $crypto;
    private $packetBuilder;
    private $packetHandlers;

    private $sentVersion = false;

    public function __construct()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->crypto = new PacketCryptoProvider;
        $this->packetBuilder = new PacketBuilder;
        $this->packetHandlers = [];
    }

    public function getCrypto(): PacketCryptoProvider
    {
        return $this->crypto;
    }

    public function getSentVersion(): bool
    {
        return $this->sentVersion;
    }

    public function connect(string $address, int $port)
    {
        socket_connect($this->socket, $address, $port);
        echo "Connected to $address:$port\n";
    }

    public function transfer(string $address, int $port)
    {
        socket_close($this->socket);
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($this->socket, $address, $port);
        $this->crypto->resetEncryptSequence();
        echo "Transferred to $address:$port\n";
    }

    public function registerPacketHandler(int $opcode, Callable $callback)
    {
        $this->packetHandlers[$opcode] = $callback;
    }

    public function sendBaramPacket(): bool
    {
        return $this->sendPacket(new Packet('baram'));
    }

    public function sendVersionCheckPacket(int $versionNumber): bool
    {
        $this->sentVersion = true;
        $versionCheckPacket = new Packet;
        $versionCheckPacket->writeByte(C_VERSION_PACKET);
        $versionCheckPacket->writeInt16($versionNumber);
        $versionCheckPacket->writeString('L');
        $versionCheckPacket->writeString('K');
        $versionCheckPacket->writeByte(0);
        return $this->sendPacket($versionCheckPacket);
    }

    public function sendLoginPacket(string $name, string $password): bool
    {
        $loginPacket = new Packet;
        $loginPacket->writeByte(C_LOGIN_PACKET);
        $loginPacket->writeString($name, STR_LENGTH_8);
        $loginPacket->writeString($password, STR_LENGTH_8);

        $loginId = new LoginId;

        $a = rand() & 0xFF;
        $b = rand() & 0xFF;

        $loginId32 = $loginId->getLoginId32();
        $loginId32XorByte = ($b + 138) & 0xFF;
        $loginId32 ^= ($loginId32XorByte++ | ($loginId32XorByte++ << 8) | ($loginId32XorByte++ << 16) | ($loginId32XorByte << 24));

        $loginId16 = $loginId->generateLoginId16();
        $loginId16XorByte = ($b + 94) & 0xFF;
        $loginId16 ^= ($loginId16XorByte++ | ($loginId16XorByte << 8));

        $randomVal = rand() & 0xFFFF;
        $randomValXorByte = ($b + 115) & 0xFF;
        $randomVal ^= ($randomValXorByte++ | ($randomValXorByte++ << 8) | ($randomValXorByte++ << 16) | ($randomValXorByte << 24));

        $loginIdBytes = chr($a);
        $loginIdBytes .= chr(($b ^ ($a + 59)) & 0xFF);

        $loginIdBytes .= chr(($loginId32 >> 24) & 0xFF);
        $loginIdBytes .= chr(($loginId32 >> 16) & 0xFF);
        $loginIdBytes .= chr(($loginId32 >> 8) & 0xFF);
        $loginIdBytes .= chr($loginId32 & 0xFF);

        $loginIdBytes .= chr(($loginId16 >> 8) & 0xFF);
        $loginIdBytes .= chr($loginId16 & 0xFF);

        $loginIdBytes .= chr(($randomVal >> 24) & 0xFF);
        $loginIdBytes .= chr(($randomVal >> 16) & 0xFF);
        $loginIdBytes .= chr(($randomVal >> 8) & 0xFF);
        $loginIdBytes .= chr($randomVal & 0xFF);

        $crc = CRC16::computeHash($loginIdBytes);
        $crcXorByte = ($b + 165) & 0xFF;
        $crc ^= ($crcXorByte++ | ($crcXorByte << 8));

        $loginIdBytes .= chr(($crc >> 8) & 0xFF);
        $loginIdBytes .= chr($crc & 0xFF);

        $loginIdBytes .= chr(1);

        $loginPacket->writeString($loginIdBytes);

        return $this->sendPacket($loginPacket);
    }

    public function sendSayPacket(string $text, int $type = SAY_NORMAL)
    {
        $sayPacket = new Packet;
        $sayPacket->writeByte(C_SAY_PACKET);
        $sayPacket->writeByte($type);
        $sayPacket->writeString($text, STR_LENGTH_8);
        return $this->sendPacket($sayPacket);
    }

    public function sendTransferServerPacket(int $cryptoSeed, string $cryptoKeystream, string $sessionName, int $sessionId): bool
    {
        $transferServerPacket = new Packet;
        $transferServerPacket->writeByte(C_TRANSFER_SERVER_PACKET);
        $transferServerPacket->writeByte($cryptoSeed);
        $transferServerPacket->writeString($cryptoKeystream, STR_LENGTH_8);
        $transferServerPacket->writeString($sessionName, STR_LENGTH_8);
        $transferServerPacket->writeInt32($sessionId);
        return $this->sendPacket($transferServerPacket);
    }

    public function sendObjectInfoRequestPacket(int $objectId)
    {
        $objectInfoRequestPacket = new Packet;
        $objectInfoRequestPacket->writeByte(C_OBJECT_INFO_REQUEST_PACKET);
        $objectInfoRequestPacket->writeByte(OBJECT_INFO_TYPE_OBJECT);
        $objectInfoRequestPacket->writeInt32($objectId);
        return $this->sendPacket($objectInfoRequestPacket);
    }

    public function sendMultiServerPacket(): bool
    {
        $multiServerPacket = new Packet;
        $multiServerPacket->writeByte(C_MULTI_SERVER_PACKET);
        $multiServerPacket->writeInt32(0);
        return $this->sendPacket($multiServerPacket);
    }

    public function sendRequestWebsitePacket(): bool
    {
        $websitePacket = new Packet;
        $websitePacket->writeByte(C_WEBSITE_PACKET);
        $websitePacket->writeByte(1);
        return $this->sendPacket($websitePacket);
    }

    public function sendPacket(Packet $packet): bool
    {
        echo 'SEND '.$packet->getHexString()."\n      ".$packet->getAsciiString()."\n";

        $data = $packet->toString();

        switch (ord($data[0])) {
            case 0x00:
            case 0x10:
            case 0x48:
                break;
            case 0x02:
            case 0x03:
            case 0x04:
            case 0x0B:
            case 0x26:
            case 0x2D:
            case 0x3A:
            case 0x42:
            case 0x43:
            case 0x4B:
            case 0x57:
            case 0x62:
            case 0x68:
            case 0x71:
            case 0x73:
            case 0x7B:
                $data = $this->crypto->encrypt($data, 0, strlen($data), false);
                break;
            default:
                $data = $this->crypto->encrypt($data, 0, strlen($data), true);
                break;
        }

        $length = strlen($data);
        $buffer = chr(STREAM_HEADER) . chr($length / 256) . chr($length % 256) . $data;

        return $this->write($buffer);
    }

    public function poll(): bool
    {
        $read = [$this->socket];
        $write = null;
        $except = null;

        if (socket_select($read, $write, $except, 0) < 1) {
            return true;
        }

        if (in_array($this->socket, $read)) {
            $buffer = socket_read($this->socket, self::READ_LENGTH);

            if ($buffer === false) {
                return false;
            }

            $this->packetBuilder->append($buffer);
            
            while ($this->packetBuilder->extract($data)) {
                switch (ord($data[0])) {
                    case 0x00:
                    case 0x03:
                    case 0x40:
                    case 0x7E:
                        break;
                    case 0x01:
                    case 0x02:
                    case 0x0A:
                    case 0x56:
                    case 0x60:
                    case 0x62:
                    case 0x66:
                    case 0x6F:
                        $data = $this->crypto->decrypt($data, 0, strlen($data), false);
                        break;
                    default:
                        $data = $this->crypto->decrypt($data, 0, strlen($data), true);
                        break;
                }

                $packet = new Packet($data);

                echo 'RECV '.$packet->getHexString()."\n     ".$packet->getAsciiString()."\n";

                $opcode = $packet->readByte();

                if (isset($this->packetHandlers[$opcode])) {
                    $this->packetHandlers[$opcode]($this, $packet);
                }
            }
        }

        return true;
    }

    private function write(string $buffer): bool
    {
        $length = strlen($buffer);
        $totalBytesSent = 0;

        do {
            $bytesToSend = substr($buffer, $totalBytesSent);
            $n = socket_write($this->socket, $bytesToSend);
            if ($n === false) {
                return false;
            }
            $totalBytesSent += $n;
        } while ($totalBytesSent < $length);

        return true;
    }
}
