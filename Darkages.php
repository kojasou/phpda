<?php

require_once __DIR__.'/bootstrap.php';

use \Darkages\Client;
use \Darkages\Packet;

$client = new Client;

$client->registerPacketHandler(S_VERSION_CHECK_PACKET, function (Client $client, Packet $packet) {
    switch ($packet->readByte()) {
        case VERSION_CHECK_OK:
            $multiServerTableCrc = $packet->readInt32();
            $cryptoSeed = $packet->readByte();
            $cryptoKeystream = $packet->readString($packet->readByte());

            $client->getCrypto()->setParams($cryptoSeed, $cryptoKeystream);
            $client->sendMultiServerPacket();
            break;
        case VERSION_CHECK_UNSUPPORTED:
            break;
        case VERSION_CHECK_PATCH:
            $versionNumber = $packet->readInt16();
            $unknown = $packet->readByte();
            $patchUrl = $packet->readString($packet->readByte());
            break;
    }
});

$client->registerPacketHandler(S_TRANSFER_SERVER, function (Client $client, Packet $packet) {
    $address = $packet->readString(4);
    $port = $packet->readInt16();
    $remainingLength = $packet->readByte();
    $cryptoSeed = $packet->readByte();
    $cryptoKeystream = $packet->readString($packet->readByte());
    $sessionName = $packet->readString($packet->readByte());
    $sessionId = $packet->readInt32();
    $client->transfer(ord($address[3]).'.'.ord($address[2]).'.'.ord($address[1]).'.'.ord($address[0]), $port);
    $client->sendTransferServerPacket($cryptoSeed, $cryptoKeystream, $sessionName, $sessionId);
});

$client->registerPacketHandler(0x3B, function (Client $client, Packet $packet) {
    $hi = $packet->readByte();
    $lo = $packet->readByte();

    $responsePacket = new Packet;
    $responsePacket->writeByte(0x45);
    $responsePacket->writeByte($lo);
    $responsePacket->writeByte($hi);

    $client->sendPacket($responsePacket);
});

$client->registerPacketHandler(S_STIPULATION_PACKET, function (Client $client, Packet $packet) {
    switch ($packet->readByte()) {
        case STIPULATION_CRC:
            $stipulationCrc = $packet->readInt32();
            $client->sendRequestWebsitePacket();
            break;
        case STIPULATION_DATA:
            $stipulationData = $packet->readString($packet->readInt16());
            break;
    }
});

$client->registerPacketHandler(S_BROWSER_PACKET, function (Client $client, Packet $packet) {
    switch ($packet->readByte()) {
        case BROWSER_OPEN_END_GAME:
        case BROWSER_OPEN:
            $url = $packet->readString($packet->readInt16());
            $text = $packet->readString($packet->readInt16());
            break;
        case BROWSER_HOMEPAGE:
            $homepageUrl = $packet->readString($packet->readByte());
            $name = readline('Name: ');
            $password = readline('Password: ');
            $client->sendLoginPacket($name, $password);
            $client->getCrypto()->generateKeystream2Table($name);
            break;
    }
});

$client->registerPacketHandler(0x68, function (Client $client, Packet $packet) {
    $serverTimeStamp = $packet->readInt32();

    $responsePacket = new Packet;
    $responsePacket->writeByte(0x75);
    $responsePacket->writeInt32($serverTimeStamp);
    $responsePacket->writeInt32(round(microtime(true) * 1000));

    $client->sendPacket($responsePacket);
});

$client->registerPacketHandler(S_CONNECTED_PACKET, function (Client $client, Packet $packet) {
    if (!$client->getSentVersion()) {
        $client->sendBaramPacket();
        $client->sendVersionCheckPacket(741);
    }
});

$client->connect(gethostbyname('da0.kru.com'), 2610);

while ($client->poll()) {
    usleep(33333);
}
