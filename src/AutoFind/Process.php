<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-24
 * Time: 12:26
 */

namespace EasySwoole\Rpc\AutoFind;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ProtocolPackage;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\Client as CoClient;

class Process extends AbstractProcess
{

    const UDP_ACTION_HEART_BEAT = 1;
    const UDP_ACTION_OFFLINE = 2;
    /** @var Config */
    protected $config;
    public function run($arg)
    {
        /** @var $arg Config */
        $this->config = $arg;
        $this->addTick(15*1000,function (){
            //每15s对外广播自己的存在
            $client = new CoClient(SWOOLE_UDP);
            $data = new ProtocolPackage();
            $data->setAction(self::UDP_ACTION_HEART_BEAT);
            $data = serialize($data);
            foreach ($this->config->getAutoFindConfig()->getAutoFindBroadcastAddress() as $address){
                $address = explode(':',$address);
                $client->sendto($address[0],$address[1],$data);
            }
        });
        if(!empty($arg->getAutoFindConfig()->getAutoFindListenAddress())){
            $address = explode(':',$arg->getAutoFindConfig()->getAutoFindListenAddress());
            $socketServer = new Socket(AF_INET,SOCK_DGRAM);
            $socketServer->bind($address[0],$address[1]);
            while (1){
                $peer = null;
                $request = unserialize($socketServer->recvfrom($peer));
                if($request instanceof ProtocolPackage){
                    switch ($request->getAction()){
                        case self::UDP_ACTION_HEART_BEAT:{
//                                $node = $request->getArg();
//                                $this->config->getNodeManager()->registerServiceNode($node);
                            break;
                        }
                        case  self::UDP_ACTION_OFFLINE:{
//                                $node = $request->getArg();
//                                $this->config->getNodeManager()->deleteServiceNode($node);
                            break;
                        }
                    }
                }else{
                    trigger_error('unserialize fail from '.$peer['address'].'@'.$peer['port']);
                }
            }
        }
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}