<?php

namespace Bluerhinos;

/*
 	phpMQTT
	A simple php class to connect/publish/subscribe to an MQTT broker

*/

/*
	Licence

	Copyright (c) 2010 Blue Rhinos Consulting | Andrew Milsted
	andrew@bluerhinos.co.uk | http://www.bluerhinos.co.uk

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

*/

/* phpMQTT */


const LOGLEVEL_DEBUG = 0;
const LOGLEVEL_INFO = 1;
const LOGLEVEL_ERROR = 2;

class phpMQTT
{
    protected $socket;            /* holds the socket	*/
    protected $msgid = 1;            /* counter for message id */
    protected $logger_function; /* callback function used for logging */
    public $keepalive = 10;        /* default keepalive timmer */
    public $timesinceping;        /* host unix time, used to detect disconects */
    public $topics = [];    /* used to store currently subscribed topics */
    public $debug = false;        /* should output debug messages */
    public $address;            /* broker address */
    public $port;                /* broker port */
    public $clientid;            /* client id sent to brocker */
    public $will;                /* stores the will of the client */
    public $username;            /* stores username */
    public $password;            /* stores password */



    public $cafile;
    protected static $known_commands = [
        1 => 'CONNECT',
        2 => 'CONNACK',
        3 => 'PUBLISH',
        4 => 'PUBACK',
        5 => 'PUBREC',
        6 => 'PUBREL',
        7 => 'PUBCOMP',
        8 => 'SUBSCRIBE',
        9 => 'SUBACK',
        10 => 'UNSUBSCRIBE',
        11 => 'UNSUBACK',
        12 => 'PINGREQ',
        13 => 'PINGRESP',
        14 => 'DISCONNECT'
    ];

    /**
     * phpMQTT constructor.
     *
     * @param $address
     * @param $port
     * @param $clientid
     * @param null $cafile
     */
    public function __construct($address, $port, $clientid, $cafile = null, $logger_function =null)
    {
        $this->broker($address, $port, $clientid, $cafile, $logger_function);
    }

    /**
     * Sets the broker details
     *
     * @param $address
     * @param $port
     * @param $clientid
     * @param null $cafile
     */
    public function broker($address, $port, $clientid, $cafile = null, $logger_function = null): void
    {
        if ($logger_function === null) {
            $this->logger_function = [$this, 'default_logger_function'];
        } else {
            $this->logger_function = $logger_function;
        }
        if (!is_callable($this->logger_function)) {
            throw new \InvalidArgumentException("Logger function is not callable");
        }

        $this->address = $address;
        $this->port = $port;
        $this->clientid = $clientid;
        $this->cafile = $cafile;
    }

    /**
     * Will try and connect, if fails it will sleep 10s and try again, this will enable the script to recover from a network outage
     *
     * @param bool $clean - should the client send a clean session flag
     * @param null $will
     * @param null $username
     * @param null $password
     *
     * @return bool
     */
    public function connect_auto($clean = true, $will = null, $username = null, $password = null): bool
    {
        while ($this->connect($clean, $will, $username, $password) === false) {
            sleep(10);
        }
        return true;
    }

    /**
     * @param bool $clean - should the client send a clean session flag
     * @param null $will
     * @param null $username
     * @param null $password
     *
     * @return bool
     */
    public function connect($clean = true, $will = null, $username = null, $password = null): bool
    {
        if ($will) {
            $this->will = $will;
        }
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }

        if ($this->cafile) {
            $socketContext = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer_name' => true,
                        'cafile' => $this->cafile
                    ]
                ]
            );
            $this->socket = stream_socket_client('tls://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT, $socketContext);
            $this->_log(LOGLEVEL_INFO, "connect: Connecting with TLS to $this->address $this->port using $this->cafile with username $this->username . Session clean :  $clean , client ID $this->clientid");
        } else {
            $this->socket = stream_socket_client('tcp://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT);
            $this->_log(LOGLEVEL_INFO, "connect: Connecting without TLS to $this->address $this->port  with username $this->username . Session clean :  $clean  , client ID $this->clientid");
        }

        if (!$this->socket) {
            $this->_log(LOGLEVEL_ERROR, "stream_socket_create() $errno, $errstr");
            return false;
        }
        $this->_log(LOGLEVEL_DEBUG, "stream_socket_created ". $this->address . ':' . $this->port);
        stream_set_timeout($this->socket, 5);
        stream_set_blocking($this->socket, 0);

        $i = 0;
        $buffer = '';

        $buffer .= chr(0x00);
        $i++; // Length MSB
        $buffer .= chr(0x04);
        $i++; // Length LSB
        $buffer .= chr(0x4d);
        $i++; // M
        $buffer .= chr(0x51);
        $i++; // Q
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x04);
        $i++; // // Protocol Level

        //No Will
        $var = 0;
        if ($clean) {
            $var += 2;
        }

        //Add will info to header
        if ($this->will !== null) {
            $var += 4; // Set will flag
            $var += ($this->will['qos'] << 3); //Set will qos
            if ($this->will['retain']) {
                $var += 32;
            } //Set will retain
        }

        if ($this->username !== null) {
            $var += 128;
        }    //Add username to header
        if ($this->password !== null) {
            $var += 64;
        }    //Add password to header

        $buffer .= chr($var);
        $i++;

        //Keep alive
        $buffer .= chr($this->keepalive >> 8);
        $i++;
        $buffer .= chr($this->keepalive & 0xff);
        $i++;

        $buffer .= $this->strwritestring($this->clientid, $i);

        //Adding will to payload
        if ($this->will !== null) {
            $buffer .= $this->strwritestring($this->will['topic'], $i);
            $buffer .= $this->strwritestring($this->will['content'], $i);
        }

        if ($this->username !== null) {
            $buffer .= $this->strwritestring($this->username, $i);
        }
        if ($this->password !== null) {
            $buffer .= $this->strwritestring($this->password, $i);
        }

        $head = chr(0x10);

        while ($i > 0) {
            $encodedByte = $i % 128;
            $i /= 128;
            $i = (int)$i;
            if ($i > 0) {
                $encodedByte |= 128;
            }
            $head .= chr($encodedByte);
        }

        fwrite($this->socket, $head, 2);
        fwrite($this->socket, $buffer);

        $string = $this->read(4);
        $this->_log(LOGLEVEL_DEBUG, 'connect: server response ' . bin2hex($string));
        $isFixedHeaderConnackOk = ord($string[0]) == 0x20 && ord($string[1]) == 0x02; //fixed due to fixed variable header
        $isVariableHeaderConnackOk = ord($string[2]) == 0x00 || ord($string[2]) == 0x01;
        $isVariableHeaderReturnCodeOk = ord($string[3]) == 0x00;
        if ($isVariableHeaderConnackOk && $isFixedHeaderConnackOk && $isVariableHeaderReturnCodeOk) {
            $this->isSessionPresent = ord($string[2]) == 0x01;
            if ($this->isSessionPresent) {
                $this->_log(LOGLEVEL_INFO, "CONNACK 0x20 0x02 received, connected to broker. Session present.");
            } else {
                $this->_log(LOGLEVEL_INFO, "CONNACK 0x20 0x02 received, connected to broker. Session not present.");
            }

        } else {
            $this->_log(LOGLEVEL_ERROR,"No valid CONNACK received. isFixedHeaderConnackOk $isFixedHeaderConnackOk , isVariableHeaderConnackOk $isVariableHeaderConnackOk., isReturnCodeOk: $isVariableHeaderReturnCodeOk Detailed bits " . bin2hex($string));
            return false;
        }

        $this->timesinceping = time();

        return true;
    }

    /**
     * Reads in so many bytes
     *
     * @param int $int
     * @param bool $nb
     *
     * @return false|string
     */
    public function read($int = 8192, $nb = false)
    {
        $string = '';
        $togo = $int;

        if ($nb) {
            return fread($this->socket, $togo);
        }

        while (!feof($this->socket) && $togo > 0) {
            $fread = fread($this->socket, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }

        return $string;
    }

    /**
     * Subscribes to a topic, wait for message and return it
     *
     * @param $topic
     * @param $qos
     *
     * @return string
     */
    public function subscribeAndWaitForMessage($topic, $qos): ?string
    {
        $this->subscribe(
            [
                $topic => [
                    'qos' => $qos,
                    'function' => '__direct_return_message__'
                ]
            ]
        );

        do {
            $return = $this->proc();
        } while ($return === true);

        return $return;
    }

	function addCallback($topics) {
		foreach($topics as $key => $topic){
			$this->topics[$key] = $topic;
		}
	}

    /**
     * subscribes to topics. Note that we support only qos 0 and 1.
     *
     * @param $topics
     */
    public function subscribe($topics): void
    {
        $i = 0;
        $buffer = '';
        $id = $this->msgid;
        $buffer .= chr($id >> 8);
        $i++;
        $buffer .= chr($id % 256);
        $i++;

        foreach ($topics as $key => $topic) {
            $buffer .= $this->strwritestring($key, $i);
            $qos = $topic['qos'];
            if ($qos > 1) {
                this->_log(LOGLEVEL_ERROR, "Unable to subscribe with qos > 1, as this client can only handle 0 and 1, falling back to 1 for $topic");
                $qos = 1;
            }
            $buffer .= chr($qos);
            $i++;
            $this->topics[$key] = $topic;
            $this->_log(LOGLEVEL_INFO, "subscribe: Subscribed for topic . $key using qos $qos");
        }

        $cmd = 0x82;

        $head = chr($cmd);
        $head .= $this->setmsglength($i);
        fwrite($this->socket, $head, strlen($head));
        $this->_fwrite($buffer);
    }

    /**
     * Sends a keep alive ping
     */
    public function ping(): void
    {
        $head = chr(0xc0);
        $head .= chr(0x00);
        fwrite($this->socket, $head, 2);
        $this->timesinceping = time();
        $this->_log(LOGLEVEL_DEBUG, 'ping sent');
    }

    /**
     *  sends a proper disconnect cmd
     */
    public function disconnect(): void
    {
        $head = ' ';
        $head[0] = chr(0xe0);
        $head[1] = chr(0x00);
        fwrite($this->socket, $head, 2);
    }

    /**
     * Sends a proper disconnect, then closes the socket
     */
    public function close(): void
    {
        $this->disconnect();
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }

    /**
     * PUBACK needs to be send as answer to PUBLISH with QoS 1.
     * @param $packetId The packet ID to confirm.
     * @return void
     */
	public function sendPuback($packetId): void{
    $buffer = '';

    // Construct the variable header (Packet Identifier)
    $buffer .= chr($packetId >> 8); // Most significant byte
    $buffer .= chr($packetId % 256); // Least significant byte

    // Construct the fixed header
    $cmd = 0x40; // PUBACK command is 0x40
    $head = chr($cmd);

    // Length of the variable header is 2 bytes (Packet Identifier)
    $head .= $this->setmsglength(2);

    // Send the PUBACK message
    fwrite($this->socket, $head, strlen($head));
    $this->_fwrite($buffer);
    $this->_log(LOGLEVEL_DEBUG, "puback sent for packet . $packetId");
}


    /**
     * Publishes $content on a $topic
     *
     * @param $topic
     * @param $content
     * @param int $qos
     * @param bool $retain
     */
    public function publish($topic, $content, $qos = 0, $retain = false): void
    {
        $i = 0;
        $buffer = '';

        $buffer .= $this->strwritestring($topic, $i);

        if ($qos) {
            $id = $this->msgid++;
            $buffer .= chr($id >> 8);
            $i++;
            $buffer .= chr($id % 256);
            $i++;
        }

        $buffer .= $content;
        $i += strlen($content);

        $head = ' ';
        $cmd = 0x30;
        if ($qos) {
            $cmd += $qos << 1;
        }
        if (empty($retain) === false) {
            ++$cmd;
        }

        $head[0] = chr($cmd);
        $head .= $this->setmsglength($i);

        fwrite($this->socket, $head, strlen($head));
        $this->_fwrite($buffer);
        LOGDEB("Written message with qos $qos to $topic ");
    }

    /**
     * Writes a string to the socket
     *
     * @param $buffer
     *
     * @return bool|int
     */
    protected function _fwrite($buffer)
    {
        $buffer_length = strlen($buffer);
        for ($written = 0; $written < $buffer_length; $written += $fwrite) {
            $fwrite = fwrite($this->socket, substr($buffer, $written));
            if ($fwrite === false) {
                return false;
            }
        }
        return $buffer_length;
    }

    /**
     * Processes an incoming message (PUBLISH)
     *
     * @param $msg
     *
     * @retrun bool|string
     */
    public function onIncomingMessage($incomingData, $qosLevel)
    {
        if ($qosLevel > 1) {
            $this->_log(LOGLEVEL_ERROR,"Got qos > 1, but we can handle only qos 0 and 1 $qosLevel");
        }
        //compute topic length
		$tlen = (ord($incomingData[0]) << 8) + ord($incomingData[1]);
        //get the topic
		$topic = substr($incomingData, 2, $tlen);

		// Update the position to start after the topic
		$remainingData = substr($incomingData, $tlen + 2);
		// If QoS level > 0, read the Packet Identifier
		$packetId = null;
		$message = null;
		if ($qosLevel > 0) {
			// Packet Identifier consists of two bytes
			$packetId = (ord($remainingData[0]) << 8) + ord($remainingData[1]);
			// Update the position to start after the Packet Identifier
            $message = substr($remainingData, 2); // Remove the first two bytes for the Packet Identifier
		} else {
            $message=$remainingData;
		}
        $found = false;
		$this->last_msg=$message;
        foreach ($this->topics as $key => $top) {
            if (preg_match(
                '/^' . str_replace(
                    '#',
                    '.*',
                    str_replace(
                        '+',
                        "[^\/]*",
                        str_replace(
                            '/',
                            "\/",
                            str_replace(
                                '$',
                                '\$',
                                $key
                            )
                        )
                    )
                ) . '$/',
                $topic
            )) {
                $found = true;

                if ($top['function'] === '__direct_return_message__') {
                    if ($qosLevel == 1 ) {
                        $this->sendPuback($packetId);
                        $this->_log(LOGLEVEL_DEBUG, "MSG with $packetId for $topic acknowledged for qos 1");
                    }
                    return $message;
                }

                if (is_callable($top['function'])) {
                    call_user_func($top['function'], $topic, $message);
                    if ($qosLevel == 1 ) {
                        $this->sendPuback($packetId);
                        $this->_log(LOGLEVEL_DEBUG, "MSG with $packetId for $topic acknowledged for qos 1");
                    }
                } else {
                    $this->_log(LOGLEVEL_ERROR, 'Message received on topic ' . $topic . ' but function is not callable.');
                }
            }
        }

        if ($found === false) {
            $this->_log(LOGLEVEL_ERROR, "msg $message received but no match in subscriptions");
        }

        return $found;
    }

    public function onSuback($fixHeader, $incomingData) {
        if (ord($fixHeader) != 0x90) {
            $this->_log(LOGLEVEL_ERROR, 'Invalid SUBACK received: header '. bin2hex($fixHeader) . ' data ' . bin2hex($incomingData));
        }
        $incomingDataLength = strlen($incomingData);
        for ($i = 0; $i < $incomingDataLength; $i++) {
            switch (ord($incomingData[$i])) {
                case 0x00:
                {
                    $this->_log(LOGLEVEL_DEBUG, 'SUBACK details: topic no.  '.$i . '  OK, max QoS 0 ');
                    break;
                }
                case 0x01:
                {
                    $this->_log(LOGLEVEL_DEBUG, 'SUBACK details: topic no.  '.$i . '  OK, max QoS 1 ');
                    break;
                }
                case 0x02:
                {
                    $this->_log(LOGLEVEL_DEBUG, 'SUBACK details: topic no.  '.$i . '  OK, max QoS 2 ');
                    break;
                }
                case 0x80:
                {
                    $this->_log(LOGLEVEL_ERROR, 'SUBACK details: topic no.  '.$i . '  error. ');
                    break;
                }
                default:
                {
                    $this->_log(LOGLEVEL_ERROR, 'SUBACK details: topic no.  '.$i . '  invalid data: ' . bin2hex($incomingData[$i]));
                    break;
                }

            }
        }
        return true;
    }

    /**
     * The processing loop for an "always on" client
     * set true when you are doing other stuff in the loop good for
     * watching something else at the same time
     *
     * @param bool $loop
     *
     * @return bool | string
     */
    public function proc(bool $loop = true)
    {
        if (feof($this->socket)) {
            $this->_log(LOGLEVEL_DEBUG, 'eof receive going to reconnect for good measure');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }

        $byte = $this->read(1, true);

        if ((string)$byte === '') {
            if ($loop === true) {
                usleep(100000);
            }
        } else {
            $logByte= bin2hex($byte);
            $this->_log(LOGLEVEL_DEBUG, "CONTROL byte received: $logByte bytes received");
			$byteIntValue = ord($byte);
            $cmd = $byteIntValue >> 4;
            $this->_log(LOGLEVEL_DEBUG,
                sprintf(
                    'Received CMD: %d (%s)',
                    $cmd,
                    isset(static::$known_commands[$cmd]) === true ? static::$known_commands[$cmd] : 'Unknown'
                )
            );

            $multiplier = 1;
            $value = 0;
            do {
                $lengthByte=$this->read(1);
                $logByte= bin2hex($lengthByte);
                $this->_log(LOGLEVEL_DEBUG, "Length byte received: $logByte bytes received");
                $digit = ord($lengthByte);
                $value += ($digit & 127) * $multiplier;
                $multiplier *= 128;
            } while (($digit & 128) !== 0);

            $this->_log(LOGLEVEL_DEBUG,'Fetching: ' . $value . ' bytes');

            $string = $value > 0 ? $this->read($value) : '';

            if ($cmd) {
                switch ($cmd) {
                    case 3: //Publish MSG
						$qosLevel = ($byteIntValue & 0b00000110) >> 1;
						$retain = ($byteIntValue & 0b00000001);
                        $return = $this->onIncomingMessage($string, $qosLevel);
                        if (is_bool($return) === false) {
                            return $return;
                        }
                        break;
                    case 9: //SUBACK
                        $return = $this->onSuback($byte, $string);
                        if (is_bool($return) === false) {
                            return $return;
                        }
                        break;

                }
            }
        }

        if ($this->timesinceping < (time() - $this->keepalive)) {
            $this->_log(LOGLEVEL_DEBUG, 'not had something in a while so ping');
            $this->ping();
        }

        if ($this->timesinceping < (time() - ($this->keepalive * 2))) {
            $this->_log(LOGLEVEL_DEBUG, 'not seen a packet in a while, disconnecting/reconnecting');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }

        return true;
    }

    /**
     * Gets the length of a msg, (and increments $i)
     *
     * @param $msg
     * @param $i
     *
     * @return float|int
     */
    protected function getmsglength(&$msg, &$i)
    {
        $multiplier = 1;
        $value = 0;
        do {
            $digit = ord($msg[$i]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $i++;
        } while (($digit & 128) !== 0);

        return $value;
    }

    /**
     * @param $len
     *
     * @return string
     */
    protected function setmsglength($len): string
    {
        $string = '';
        do {
            $digit = $len % 128;
            $len >>= 7;
            // if there are more digits to encode, set the top bit of this digit
            if ($len > 0) {
                $digit |= 0x80;
            }
            $string .= chr($digit);
        } while ($len > 0);
        return $string;
    }

    /**
     * @param $str
     * @param $i
     *
     * @return string
     */
    protected function strwritestring($str, &$i): string
    {
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len + 2);
        return $ret;
    }

    /**
     * Prints a sting out character by character
     *
     * @param $string
     */
    public function printstr($string): void
    {
        $strlen = strlen($string);
        for ($j = 0; $j < $strlen; $j++) {
            $num = ord($string[$j]);
            if ($num > 31) {
                $chr = $string[$j];
            } else {
                $chr = ' ';
            }
            printf("%4d: %08b : 0x%02x : %s \n", $j, $num, $num, $chr);
        }
    }

    protected function _log(int $logLevel, string $message) {
        call_user_func($this->logger_function, $logLevel, $message);
    }

    function default_logger_function(int $logLevel, string $message) {
        switch ($logLevel) {
            case LOGLEVEL_INFO:
            case LOGLEVEL_DEBUG:
            {
                echo date('r: ') . $message . PHP_EOL;
                break;
            }
            case LOGLEVEL_ERROR: {
                error_log('Error:' . $message);
                break;
            }
            default: {
                error_log('Error:' . $message);
                break;
            }
        }
    }
}
