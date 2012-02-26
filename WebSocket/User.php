<?php
 
namespace WebSocket;

use Exception;

/**
 * This is the user who is connected to the websocket.
 */
class User
{
	private $socket;
	
	private $handshaked = false;
	
	private $buffer;
		
	/**
	 * @param mixed $socket
	 */
	public function __construct($socket)
	{
		$this->socket = $socket;
	}
	
	/**
	 * @return mixed
	 */
	public function getSocket()
	{
		return $this->socket;
	}
	
	/**
	 * @return boolean
	 */
	public function hasHandshaked()
	{
		return $this->handshaked;
	}
	
	/**
	 * Do the handshake
	 *
	 * @param string $data
	 */
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
	
	/**
	 * Write data to the user
	 *
	 * @param mixed $data
	 */
	public function write($data)
	{
		return socket_write($this->socket, $data, strlen($data));
	}
	
	/**
	 * Create the buffer for fragmented frames
	 *
	 * @param \WebSocket\Frame $frame
	 */
	public function createBuffer(Frame $frame)
	{
		$this->buffer = $frame;
	}
	
	/**
	 * Append data to the buffer for fragmented frames
	 *
	 * @param \WebSocket\Frame $frame
	 */
	public function appendBuffer($frame)
	{
		$this->buffer->appendData($frame->getData());
	}
	
	/**
	 * Clear the buffer of this user
	 */
	public function clearBuffer()
	{
		$this->buffer = null;
	}
	
	/**
	 * Return the complete message of the user
	 * 
	 * @return mixed
	 */
	public function getBuffer()
	{
		return $this->buffer;
	}
}