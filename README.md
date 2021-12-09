# JokerSwoole
一个基于Swoole的分布式Websocket框架  
A distributed Websocket framework based on Swoole

---

composer install 安装类库即可

---

## 使用示例

```
require_once 'vendor/autoload.php';

$config = new Joker\Swoole\Config();
$config->swoolePort = 9502;
$config->redisHost = '10.0.0.201';
$server = new \Joker\Swoole\Server($config);

$server->onOpen(function(\Joker\Swoole\Facade\Server $server, int $socketId){

});

$server->onMessage(function(\Joker\Swoole\Facade\Server $server, int $socketId, array $data){

});

$server->onClose(function(\Joker\Swoole\Facade\Server $server, int $socketId){

});

$this->server->start();

```

可以参考tests下的main.php

---

## Facade

框架封装了一些便捷的门面类，使用方式如下：
``` 
public function close(\Joker\Swoole\Facade\Server $server, int $socketId){
    $abc = $server->facade()->redis()->get('abc');
    $server->facade()->mysql()->table('dealers')->insert(['code' => $socketId]);
}
```

## 心跳机制

为了保证TCP连接不被系统强行断开，框架内部加入了心跳机制。

框架将每10秒向客户端发送Ping的信息（携带id号），客户端需要在下一次Ping发送前响应本次心跳，向服务端发送Pong的消息。

框架除了第一次发送Ping外，每一次发送Ping前都会判断客户端是否成功响应了上一次的Ping，如果没有成功响应，则会强行与该客户端断开。

---

## 向客户端发送数据的结构

### 业务消息
``` 
{
    "code": 0,
    "msg": "success",
    "data": {
        ... //业务数据
    }
}

\Joker\Swoole\Facade\Server->push($socketId, array $data) 该方法发送的$data数据会被包含在上面结构中data中。
``` 

### 错误消息
``` 
{
    "code": -1,
    "msg": "错误信息", //例如：消息格式错误。
    "data": {}
}
``` 

### 心跳消息
``` 
{
    "code": 999,
    "msg": "success",
    "data": {
        "id": "xxxxxxx"
    }
}
``` 
客户端必须在一定时间内响应此心跳消息，否则链接将被自动断开。

---

## 接收客户端的数据结构

### 业务消息
``` 
{
    "code": 0,
    "data": {
        ... //业务数据
    }
}

$server->onMessage(function(\Joker\Swoole\Facade\Server $server, int $socketId, array $data){})
onMessage中的$data为上面结构中的data。
``` 

### 心跳消息
``` 
{
    "code": 999,
    "data": {
        "id": "xxxxxxx"
    }
}
```
此id为框架发送的心跳消息中的id，框架会校验此id的合法性。


## 通信安全

### 验签

```
$config = new Joker\Swoole\Config();
$config->enableCheckSign = true; //开启验签，开启后将对所有Message信息进行验签
$server = new \Joker\Swoole\Server($config);
```

客户端需要在最外层结构增加s字段：
```
{
    "code": 0,
    "s": "abcde...", //签名
    "data": {
        ... //业务数据
    }
}
```

签名规则：  
总长度74位，分为三部分，第一部分为32位的盐值，第二部分为10位的时间戳，第三部分为32位的签名  
盐值：将第二部分的时间戳进行md5（小写）生成  
时间戳：当前10位秒级时间戳  
签名：先取出时间戳最后一位数字n（时间戳尾数为0时n=1），将时间戳和盐值连接获得字符串s，将s进行n次MD5(小写)获得签名  

注意：只要出现一次签名错误，该连接将被视为非法，连接将被断开。