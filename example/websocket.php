<?php

require_once '../WebSocket/Server.php';
require_once '../WebSocket/User.php';
require_once '../WebSocket/Frame.php';

use WebSocket\Server;

$server = new Server('127.0.0.1', 9988);
$server->process();