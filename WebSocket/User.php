<?php

namespace WebSocket;

use Exception;

class User
{
	private $socket;
	
	private $handshaked = false;
	
	private $bufferType;
	private $buffer;
	
	const MAX_BUFFER_SIZE = 1048576;

	public function __construct($socket)
	{
		$this->socket = $socket;
	}
	
	public function getSocket()
	{
		return $this->socket;
	}
	
	public function hasHandshaked()
	{
		return $this->handshaked;
	}
	
	public function doHandshake($data)
	{
		if ($this->hasHandshaked())
			return;
		
		$requestHeaders = array();

		foreach(explode("\r\n", $data) as $line) {
			@list($k, $v) = explode(':', $line, 2);
			$requestHeaders[$k] = ltrim($v);
		}

		if (empty($requestHeaders['Sec-WebSocket-Key'])) {
			return;
		}
		
		$key = base64_encode(sha1($requestHeaders['Sec-WebSocket-Key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

		$response = "HTTP/1.1 101 Switching Protocols\r\n"
			. "Upgrade: WebSocket\r\n"
			. "Connection: Upgrade\r\n"
			. "Sec-WebSocket-Accept: " . $key . "\r\n"
			. "\r\n";

		if ($this->write($response))
			$this->handshaked = true;	
	}
	
	public function write($data)
	{
		return socket_write($this->socket, $data, strlen($data));
	}
	
	public function createBuffer($frame)
	{
		$this->bufferType = $frame['opcode'];
		$this->buffer = $frame['data'];
	}
	
	public function appendBuffer($frame)
	{
		$this->buffer .= $frame['data'];
		
		if (strlen($this->buffer) > self::MAX_BUFFER_SIZE)
			$this->clearBuffer();
	}
	
	public function clearBuffer()
	{
		$this->buffer = '';
		$this->bufferType = false;
	}
	
	public function getBuffer()
	{
		return $this->buffer;
	}
	
	public function getBufferType()
	{
		return $this->bufferType;
	}
}