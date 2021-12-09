<?php

require_once 'vendor/autoload.php';

class test{

    private static $ip;

    private $server;
    public function __construct()
    {
        $ips = swoole_get_local_ip();
        self::$ip = array_shift($ips);

        $config = new Joker\Swoole\Config();
        $config->swoolePort = 9502;
        $config->redisHost = '10.0.0.221';

        $config->mysqlHost = '10.0.0.141';
        $config->mysqlDatabase = 'test';
        $config->mysqlUsername = 'vm';

        $config->swooleWorkerNum = 6;
        $config->enableCheckSign = true;
        $config->debug = true;

        $this->server = new \Joker\Swoole\Server($config);

        $this->server->onOpen([$this, 'open']);
        $this->server->onMessage([$this, 'message']);
        $this->server->onClose([$this, 'close']);

        $this->server->start();
    }

    public function open(\Joker\Swoole\Facade\Server $server, int $socketId){
        $server->facade()->redis()->incr('JokerIncrOpen');
    }

    public function close(\Joker\Swoole\Facade\Server $server, int $socketId){

        $roomNo = $server->facade()->redis()->get($socketId);
        if (empty($roomNo)){
            return;
        }
        $masterSocketStr = $server->facade()->redis()->hGet('room'.$roomNo, 'master');
        $masterSocketArr = explode('|||', $masterSocketStr);
        $masterSocketId = $masterSocketArr[0];
        if ($masterSocketId != $socketId){
            $server->push($masterSocketId, ['msg' => sprintf('%d[%s]已退出房间.', $socketId, self::$ip)]);
        }
    }

    public function message(\Joker\Swoole\Facade\Server $server, int $socketId, array $data){
//        var_dump($socketId. ':'. $server->facade()->mysql()->transactionLevel());
//        $count = $server->facade()->mysql()->table('dealers')->count();
//        var_dump($count);
//        var_dump('数据库连接成功,10秒后进行第一次beginTransaction');
//        sleep(10);
        $server->facade()->mysql()->beginTransaction();
//        var_dump('第一次beginTransaction成功,10秒后测试第二次beginTransaction');
//        var_dump('第一次beginTransaction成功,10秒后测试SQL');
//        sleep(10);
//        $server->facade()->mysql()->beginTransaction();
//        var_dump('第二次beginTransaction成功,10秒后测试sql');
//        sleep(10);
//        var_dump($socketId. ':'. $server->facade()->mysql()->transactionLevel());
        $server->facade()->mysql()->table('dealers')->insert(['code' => $socketId]);
        if ($socketId % 3 == 0){
            echo "socketId{$socketId}数据插入期望成功。\n";
//            echo "10秒后将执行commit\n";
//            sleep(10);
            $server->facade()->mysql()->commit();
        }else{
            echo "socketId{$socketId}数据插入期望失败。\n";
        }
//        var_dump($socketId. ':'. $server->facade()->mysql()->transactionLevel());
//        echo "\n";

        if ($data['type'] == 1){
            //加入房间
            echo "加入房间成功，10s后调用Redis\n";
            sleep(10);
            $roomNo = $data['roomNo'];
            $server->facade()->redis()->set($socketId, $roomNo);
            $masterSocketStr = $server->facade()->redis()->hGet('room'.$roomNo, 'master');
            if (empty($masterSocketStr)){
                $server->push($socketId, ['msg' => '不存在room'. $roomNo. '，您已断开连接']);
                $server->close($socketId);
                return;
            }
            $masterSocketArr = explode('|||', $masterSocketStr);
            $masterSocketId = $masterSocketArr[0];
            $masterSocketIp = $masterSocketArr[1];

            $server->push($socketId, ['msg' => sprintf('您的id为%d[%s],您已加入%d[%s]的房间.', $socketId, self::$ip, $masterSocketId, $masterSocketIp)]);
            $server->push($masterSocketId, ['msg' => sprintf('%d[%s]加入了您的房间', $socketId, self::$ip)]);
        }elseif ($data['type'] == 2){
            //创建房间
            $roomNo = mt_rand(1000, 9999);
            $server->facade()->redis()->set($socketId, $roomNo);
            $server->facade()->redis()->hSet('room'.$roomNo, 'master', $socketId. '|||'. self::$ip);
            $server->push($socketId, ['msg' => sprintf('恭喜您成功创建房间，你的id为%d[%s]', $socketId, self::$ip)]);
            $server->push($socketId, ['type' => 1,'roomNo' => $roomNo]);
        }

//        echo "10秒后将执行垃圾回收\n";
//        sleep(10);
    }
}

new test();