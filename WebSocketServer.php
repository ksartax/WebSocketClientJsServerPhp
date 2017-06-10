<?php

include_once 'Users.php';

class WebSocketServer
{
    private $port;
    private $host;
    private $socketServer;
    private $Users;

    public function __construct($port = 9000, $host = "0")
    {
      $this->port = $port;
      $this->host = $host;
      $this->Users = new Users();
      date_default_timezone_set('Europe/Warsaw');
    }

    public function __get($name){
      return $this->$name;
    }

    private function initServer()
    {
      if(!($this->socketServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
          throw new Exception("initServer : " . socket_strerror(socket_last_error()), 1);
      }
    }

    private function setSocketOptions()
    {
      if (!socket_set_option($this->socketServer, SOL_SOCKET, SO_REUSEADDR, 1)) {
          throw new Exception("setSocketOptions : " . socket_strerror(socket_last_error()) , 1);
      }
    }

    private function setSocketBind()
    {
      if(!socket_bind($this->socketServer, $this->host, $this->port)){
        throw new Exception("setSocketBind : " . socket_strerror(socket_last_error()) , 1);
      }
    }

    private function setSicketListen()
    {
      if(!socket_listen($this->socketServer)){
        throw new Exception("setSicketListen : " . socket_strerror(socket_last_error()) , 1);
      }
    }

    private function setCloseServer()
    {
      socket_close($this->socketServer);
    }

    private function perform_handshaking($receved_header, $client_conn)
    {
    	$headers = array();
    	$lines = preg_split("/\r\n/", $receved_header);
    	foreach($lines as $line)
    	{
    		$line = chop($line);
    		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
    		{
    			$headers[$matches[1]] = $matches[2];
    		}
    	}

    	$secKey = $headers['Sec-WebSocket-Key'];
    	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    	//hand shaking header
    	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
    	"Upgrade: websocket\r\n" .
    	"Connection: Upgrade\r\n" .
    	"WebSocket-Origin: $this->host\r\n" .
    	"WebSocket-Location: ws://$this->host:$this->port/websocket/server.php\n".
    	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    	socket_write($client_conn,$upgrade,strlen($upgrade));
    }

    private function mask($text)
    {
    	$b1 = 0x80 | (0x1 & 0x0f);
    	$length = strlen($text);

    	if($length <= 125)
    		$header = pack('CC', $b1, $length);
    	elseif($length > 125 && $length < 65536)
    		$header = pack('CCn', $b1, 126, $length);
    	elseif($length >= 65536)
    		$header = pack('CCNN', $b1, 127, $length);
    	return $header.$text;
    }

    private function unmask($text) {
    	$length = ord($text[1]) & 127;
    	if($length == 126) {
    		$masks = substr($text, 4, 4);
    		$data = substr($text, 8);
    	}
    	elseif($length == 127) {
    		$masks = substr($text, 10, 4);
    		$data = substr($text, 14);
    	}
    	else {
    		$masks = substr($text, 2, 4);
    		$data = substr($text, 6);
    	}
    	$text = "";
    	for ($i = 0; $i < strlen($data); ++$i) {
    		$text .= $data[$i] ^ $masks[$i%4];
    	}
    	return $text;
    }

    private function sendAll($text)
    {
      if(!empty($text)){
        foreach ($this->Users->users as $key => $valueSend) {
          $this->send($valueSend->identifi, $text);
        }
      }
    }

    private function send($socket, $text)
    {
      socket_write($socket,$text,strlen($text));
    }

    private function disconnectUser($socket)
    {
      socket_close($socket);
      $this->Users->removeUser($socket);
    }

    private function getNick($text)
    {
        $pom = explode("!nick=",$text);
        return $pom[1];
    }

    private function protocolMappingRouter($text, $socket)
    {
      $usersAll = [];
      if($text == "disconnect"){
        $this->disconnectUser($socket->identifi);
        $text = "Rozłączony : " . $socket->nick;
      }
      if(strpos($text, "!nick=") !== false){
          $socket->nick = $this->getNick($text);
          $text = "Polączony : " . $socket->nick;
      }

      return [
        "text" => $text,
        "users" => [
          'users' => $this->Users->users,
          'count' => count($this->Users->users)
        ]
      ];
    }

    private function prepareResponseData(User $user, $text)
    {
      return $response_text = $this->mask(
        json_encode(
          array(
            'type'=>'usermsg',
            'name'=>"$user->nick",
            'color' => "$user->color",
            'date' => "" . date("H:i:s") . "",
            'message' => $text['text'],
            'usersAll' => $text['users']
          )
        )
      );
    }

    private function receiveData()
    {

      foreach ($this->Users->users as $key => $value) {

        $response_text = "";

        while(@socket_recv($value->identifi, $buf, 1024, 0) >= 1) {

              $received_text = $this->protocolMappingRouter($this->unmask($buf), $value);

              $response_text = $this->prepareResponseData($value, $received_text);
        }

        $this->sendAll($response_text);

      }
    }

    private function acceptUser()
    {
      if($newSocket = socket_accept($this->socketServer)){
        $this->Users->addUser($newSocket, "null");
        $header = socket_read($newSocket, 1024);
        $this->perform_handshaking($header, $newSocket);
      }
    }

    public function run()
    {
      try {
        $this->initServer();
        $this->setSocketOptions();
        $this->setSocketBind();
        $this->setSicketListen();

        socket_set_nonblock ($this->socketServer);

        while (true) {
            $this->acceptUser();
            $this->receiveData();
        }

      } catch (Exception $e) {
          echo $e->getMessage();
      }
    }

}


 ?>
