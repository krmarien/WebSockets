<?php

namespace WebSocket;

use Exception;

/**
 * This is the user who is connected to the websocket.
 */
class User
{
    private $_socket;

    private $_handshaked = false;

    private $_buffer;

    private $_extraData;

    /**
     * @param mixed $socket
     */
    public function __construct($socket)
    {
        $this->_socket = $socket;
    }

    /**
     * @return mixed
     */
    public function getSocket()
    {
        return $this->_socket;
    }

    /**
     * @return boolean
     */
    public function hasHandshaked()
    {
        return $this->_handshaked;
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
            $this->_handshaked = true;
    }

    /**
     * Write data to the user
     *
     * @param mixed $data
     */
    public function write($data)
    {
        return socket_write($this->_socket, $data, strlen($data));
    }

    /**
     * Create the buffer for fragmented frames
     *
     * @param \WebSocket\Frame $frame
     */
    public function createBuffer(Frame $frame)
    {
        $this->_buffer = $frame;
    }

    /**
     * Append data to the buffer for fragmented frames
     *
     * @param \WebSocket\Frame $frame
     */
    public function appendBuffer($frame)
    {
        $this->_buffer->appendData($frame->getData());
    }

    /**
     * Clear the buffer of this user
     */
    public function clearBuffer()
    {
        $this->_buffer = null;
    }

    /**
     * Return the complete message of the user
     *
     * @return mixed
     */
    public function getBuffer()
    {
        return $this->_buffer;
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return \WebSocket\User
     */
    public function setExtraData($key, $value)
    {
        $this->_extraData[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getExtraData($key)
    {
        return isset($this->_extraData[$key]) ? $this->_extraData[$key] : null;
    }

    /**
     * @param string $key
     *
     * @return \WebSocket\User
     */
    public function removeExtraData($key)
    {
        unset($this->_extraData[$key]);
        return $this;
    }
}