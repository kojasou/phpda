<?php

const STREAM_HEADER = 0xAA;

const C_VERSION_PACKET             = 0x00;
const C_LOGIN_PACKET               = 0x03;
const C_SAY_PACKET                 = 0x0E;
const C_TRANSFER_SERVER_PACKET     = 0x10;
const C_OBJECT_INFO_REQUEST_PACKET = 0x43;
const C_MULTI_SERVER_PACKET        = 0x57;
const C_BARAM_PACKET               = 0x62;
const C_WEBSITE_PACKET             = 0x68;

const S_VERSION_CHECK_PACKET = 0x00;
const S_TRANSFER_SERVER      = 0x03;
const S_STIPULATION_PACKET   = 0x60;
const S_BROWSER_PACKET       = 0x66;
const S_CONNECTED_PACKET     = 0x7E;

const SAY_NORMAL = 0;
const SAY_SHOUT  = 1;

const OBJECT_INFO_TYPE_OBJECT = 1;
const OBJECT_INFO_TYPE_STATIC = 3;

const VERSION_CHECK_OK          = 0;
const VERSION_CHECK_UNSUPPORTED = 1;
const VERSION_CHECK_PATCH       = 2;

const STIPULATION_CRC  = 0;
const STIPULATION_DATA = 1;

const BROWSER_OPEN_END_GAME = 1;
const BROWSER_OPEN          = 2;
const BROWSER_HOMEPAGE      = 3;

const STR_LENGTH_NONE = 0;
const STR_LENGTH_8    = 1;
const STR_LENGTH_16   = 2;
