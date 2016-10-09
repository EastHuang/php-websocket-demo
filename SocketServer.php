<?php
class SocketServer
{
	private $socket;
	private $readGroup = array();									//保存读的套接字
	private $writeGroup = array();									//保存写的套接字
	private $except = array();
	private $mcrypt_key = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';	//websocket协议中用于加密的字符串
	private $test = false;

	/**
	*初始化
	*@param String $host ip地址
	*@param int $port 端口
	*@param int $backlog 最大连接数
	*/
	public function __construct($host = '127.0.0.1',$port = '8080', $backlog = 10)
	{
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die('socket创建失败');
		socket_bind($this->socket,$host,$port);
		socket_listen($this->socket,$backlog);
		$this->readGroup[] = $this->socket; 	//将所有套接字存于该数组	
	}

	public function start()
	{
		while(true)
		{
			//socket_select会移除了数组元素，所以必须用临时变量重置数组
			$socketArr = $this->readGroup;  
			//阻塞，直到捕获到变化
			socket_select($socketArr, $this->writeGroup , $this->except , 3600);
			//遍历读的套接数组
			foreach($socketArr as $socket)
			{
				//如果是当前服务器的监听连接
				if($this->socket == $socket)
				{
					$client = socket_accept($this->socket);
					//添加客户端套接字
					$this->add_client($client);					
				}
				else
				{
					//获取客户端发送来的信息
					$msg = @socket_read($socket,1024);								
					//如果检测到客户端发送的是握手协议,则向客户端发送握手协议
					if(empty($msg))
					{
						continue;
					}
					else if(preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $msg,$matches))						
					{
						$upgrade = $this->createHandShake($matches[1]);
						socket_write($socket,$upgrade,strlen($upgrade));
					}
					else
					{
						//解码客户端发送的消息
						$client_info = $this->decodeMsg($msg);
						//向其他客户端进行广播
						$this->send_to_other($client_info,$socket);
					}
				}
			}
		}
	}


	/**
	*@param resource $client 客户端的套接字
	*/
	private function add_client($client)
	{
		$this->readGroup[] = $client;
	}

	/**
	*发送到指定客户端
	*@param resource $socket 客户端套接字
	*@param String $data 要发送的消息
	*/
	private function send_to_one($socket,$data)
	{
		//先对信息进行编码
		$data = $this->encodeMsg($data);
		socket_write($socket, $data);
	}

	/**
	*广播至所有客户端
	*@param String $data
	*/
	private function send_to_all($data)
	{
		$writeGroup = $this->readGroup;
		unset($writeGroup[0]);				//除去服务器自身
		$data = $this->encodeMsg($data);
		foreach($writeGroup as $socket)
		{
			socket_write($socket, $data);
		}
	}

	/**
	*广播至除发送消息外的客户端
	*@param String $data
	*@param resource $client 
	*/
	private function send_to_other($data,$client)
	{
		$writeGroup = $this->readGroup;
		unset($writeGroup[0]);				//除去服务器自身
		$data = $this->encodeMsg($data);
		foreach($writeGroup as $socket)
		{
			if($socket != $client)
			{
				socket_write($socket, $data);
			}
		}

	}

	/**
	*计算返回客户端的协议
	*@param String $msg 客户端发送过来的协议
	*@return String $upgrade 返回给客户端的协议
	*/
	private function createHandShake($client_key)
	{
		$key = base64_encode(sha1($client_key.$this->mcrypt_key,true));
		$upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
					"Upgrade: websocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: " . $key . "\r\n\r\n";		//结尾一定要两个\r\n\r\n
		return $upgrade;
	}

	/**
	*解码客户端发送过来的信息
	*@param binary $buffer 客户端传来的信息
	*@return String $decoded 解码后的字符串
	*/
	private function decodeMsg($buffer)
	{
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);

        }
        else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        }
        else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        //
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
	}

	/**
	*发送到服务端前进行编码
	*/
	private function encodeMsg($msg)
	{
        $head = str_split($msg, 125);
        if (count($head) == 1){
            return "\x81" . chr(strlen($head[0])) . $head[0];
        }
        $info = "";
        foreach ($head as $value){
            $info .= "\x81" . chr(strlen($value)) . $value;
        }
        return $info;
	}
}