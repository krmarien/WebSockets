<?php

namespace WebSocket;

use Exception;

/**
 * This is the server to handle all requests by the websocket protocol.
 */
class Server
{

	private $address;
	private $port;
	
	private $users;
	private $sockets;
	
	const OP_CONT = 0x0;
	const OP_TEXT = 0x1;
	const OP_BIN = 0x2;
	const OP_CLOSE = 0x8;
	const OP_PING = 0x9;
	const OP_PONG = 0xa;
	
	/**
	 * @param string $address The url for the websocket master socket
	 * @param integer $port The port to listen on
	 */
	public function __construct($address, $port)
	{
		$this->address = $address;
		$this->port = $port;
		$this->users = array();
		$this->sockets = array();
				
		$this->createSocket();
	}
	
	/**
	 * Create the master socket
	 */
	private function createSocket()
	{
		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		
		if (!$this->master)
			throw new Exception('Socket could not be created: ' . socket_last_error());
		
		$this->sockets[] = $this->master;
		
		if (!socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1))
			throw new Exception('Socket options could not be set: ' . socket_last_error());
		
		if (!socket_bind($this->master, $this->address, $this->port))
			throw new Exception('Socket could not be binded to given address: ' . socket_last_error());
		
		if (!socket_listen($this->master, 20))
			throw new Exception('Could not listen to socket: ' . socket_last_error());		
	}
	
	/**
	 * Start listening on master socket and user sockets
	 */
	public function process()
	{
		while(true){
			$changed = $this->sockets;
			socket_select($changed, $write, $except, null);
			
			foreach($changed as $socket){
				if ($socket == $this->master) {
					$this->_addUserSocket(socket_accept($this->master));
				} else {
					$bytes = @socket_recv($socket, $buffer, 2048, 0);
					if ($bytes == 0) {
						$this->_removeUserSocket($socket);
					} else {
						$user = $this->getUserBySocket($socket);
						if ($user->hasHandshaked()) {
							$this->_processFrame($user, $buffer);
						} else {
							$user->doHandShake($buffer);
							if ($user->hasHandshaked())
								$this->onConnect($user);
						}
					}
				}
			}
		}
	}
	
	/**
	 * Add a user socket to listen to
	 *
	 * @param mixed $socket
	 */
	private function _addUserSocket($socket)
	{
		if (!$socket)
			return;
		$this->users[] = new User($socket);
		$this->sockets[] = $socket;
	}
	
	/**
	 * Get a user by his socket
	 *
	 * @param mixed $socket
	 * @return \WebSocket\User
	 */
	public function getUserBySocket($socket)
	{
		foreach($this->users as $user) {
			if ($user->getSocket() == $socket)
				return $user;
		}
	}
	
	/**
	 * Remove a user socket
	 *
	 * @param mixed $socket
	 */
	private function _removeUserSocket($socket)
	{
		foreach($this->users as $key => $value) {
			if ($value->getSocket() == $socket)
				unset($this->users[$key]);
		}
		
		foreach($this->sockets as $key => $value) {
			if ($value == $socket)
				unset($this->sockets[$key]);
		}
	}
	
	/**
	 * Process a frame send by a user to the master socket
	 *
	 * @param \WebSocket\User $user
	 * @param mixed $data
	 */
	private function _processFrame(User $user, $data)
	{
		$f = new Frame($data);
		
		/* unfragmented message */
		if ($f->getIsFin() && $f->getOpcode() != 0) {
			/* unfragmented messages may represent a control frame */
			if ($f->getIsControl()) {
				$this->_handleControlFrame($user, $f);
			} else {
				$this->handleDataFrame($user, $f);
			}
		}
		/* start fragmented message */
		else if (!$f->getIsFin() && $f->getOpcode() != 0) {
			$user->createBuffer($f);
		}
		/* continue fragmented message */
		else if (!$f->getIsFin() && $f->getOpcode() == 0) {
			$user->appendBuffer($f);
		}
		/* finalize fragmented message */
		else if ($f->getIsFin() && $f->getOpcode() == 0) {
			$user->appendBuffer($f);
			
			$this->handleDataFrame($user, $user->getBuffer());
			
			$user->clearBuffer();
		}
	}
	
	/**
	 * Handle the received control frames
	 *
	 * @param \WebSocket\User $user
	 * @param \WebSocket\Frame $frame
	 */
	private function _handleControlFrame(User $user, Frame $frame)
	{
		$len = strlen($frame->getData());
		
		if ($frame->getOpcode() == self::OP_CLOSE) {
			/* If there is a body, the first two bytes of the body MUST be a
			 * 2-byte unsigned integer */
			if ($len !== 0 && $len === 1) {
				return;
			}
			
			$statusCode = false;
			$reason = false;
			
			if ($len >= 2) {
				$unpacked = unpack('n', substr($frame->getData(), 0, 2));
				$statusCode = $unpacked[1];
				$reason = substr($frame->getData(), 3);
			}
						
			/* Send close frame.
			* 0x88: 10001000 fin, opcode close */
			$user->write(chr(0x88) . chr(0));
			
			$this->onClose($user, $statusCode, $reason);
			$this->_removeUserSocket($user->getSocket());
		}
	}
	
	/**
	 * Handle a received data frame
	 *
	 * @param \WebSocket\User $user
	 * @param \WebSocket\Frame $frame
	 */
	protected function handleDataFrame(User $user, Frame $frame)
	{
		if ($frame->getOpcode() == self::OP_TEXT) {
			$this->gotText($user, $frame->getData());
		} else if ($frame->getOpcode() == self::OP_BIN) {
			$this->gotBin($user, $frame->getData());
		}
	}
	
	/**
	 * Send text to a user socket
	 *
	 * @param \WebSocket\User $user
	 * @param string $text 
	 */
	public function sendText($user, $text)
	{
		$len = strlen($text);
		
		/* extended 64bit payload not implemented yet */
		if ($len > 0xffff) {
			return;
		}
		
		/* 0x81 = first and last bit set (fin, opcode=text) */
		$header = chr(0x81);
		
		/* extended 32bit payload */
		if ($len >= 125) {
			$header .= chr(126) . pack('n', $len);
		} else {
			$header .= chr($len);
		}
		
		$user->write($header . $text);
	}
	
	/**
	 * Send text to all user socket
	 *
	 * @param string $text 
	 */
	public function sendTextToAll($text)
	{
		$len = strlen($text);
		
		/* extended 64bit payload not implemented yet */
		if ($len > 0xffff) {
			return;
		}
		
		/* 0x81 = first and last bit set (fin, opcode=text) */
		$header = chr(0x81);
		
		/* extended 32bit payload */
		if ($len >= 125) {
			$header .= chr(126) . pack('n', $len);
		} else {
			$header .= chr($len);
		}
		
		foreach($this->users as $user)
			$user->write($header . $text);
	}
	
	/**
	 * Parse received text
	 *
	 * @param \WebSocket\User $user
	 * @param string $data
	 */
	protected function gotText(User $user, $data)
	{
		$this->sendText($user, 'Your message to the server was: \'' . $data . '\'');
	}
	
	/**
	 * Parse received binary
	 *
	 * @param \WebSocket\User $user
	 * @param mixed $data
	 */
	protected function gotBin(User $user, $data)
	{
	}
	
	/**
	 * Do action when user closed his socket
	 *
	 * @param \WebSocket\User $user
	 * @param integer $statusCode
	 * @param string $reason
	 */
	protected function onClose(User $user, $statusCode, $reason)
	{
	}
	
	/**
	 * Do action when a new user has connected to this socket
	 *
	 * @param \WebSocket\User $user
	 */
	protected function onConnect(User $user)
	{
	}
}