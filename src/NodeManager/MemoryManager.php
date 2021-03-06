<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Server\ServiceNode;
use Swoole\Table;

class MemoryManager implements NodeManagerInterface
{
    protected $table;

    protected $ttl;

    function __construct(int $ttl = 30)
    {
        $this->ttl = $ttl;
        $this->table = new Table(2048);
        $this->table->column('service',Table::TYPE_STRING,48);
        $this->table->column('ip',Table::TYPE_STRING,16);
        $this->table->column('port',Table::TYPE_INT,4);
        $this->table->column('version',Table::TYPE_INT,4);
        $this->table->column('lastHeartbeat',Table::TYPE_INT,10);
        $this->table->column('lastFailTime',Table::TYPE_INT,10);
        $this->table->create();
    }
    function getNodes(string $serviceName, ?int $version = null): array
    {
        $fails = [];
        $hits = [];
        $time = time();
        foreach ($this->table as $nodeId =>$value){
            if($time - $value['lastHeartbeat'] > $this->ttl){
                $fails[] = $nodeId;
                continue;
            }
            if($value['service'] === $serviceName)
            {
                if($version !== null && $version === $value['version']){
                    $value = new ServiceNode($value);
                    $value->setNodeId($nodeId);
                    $hits[$nodeId] = $value;
                }else{
                    $value = new ServiceNode($value);
                    $value->setNodeId($nodeId);
                    $hits[] = $value;
                }
            }
        }
        if(!empty($fails)){
            foreach ($fails as $failKey){
                $this->table->del($failKey);
            }
        }
        return $hits;
    }

    function getNode(string $serviceName, ?int $version = null): ?ServiceNode
    {
        $list = $this->getNodes($serviceName,$version);
        if(empty($list)){
           return null;
        }
        $all = count($list);
        mt_srand(microtime(true));
        $index = rand(0,$all - 1);
        return $list[$index];
    }

    function offline(ServiceNode $serviceNode): bool
    {
        $this->table->del($this->getTableKey($serviceNode));
        return true;
    }

    function alive(ServiceNode $serviceNode): bool
    {
        $info = [
            'service'=>$serviceNode->getService(),
            'ip'=>$serviceNode->getIp(),
            'port'=>$serviceNode->getPort(),
            'version'=>$serviceNode->getVersion(),
            'lastHeartbeat'=>time()
        ];
        $this->table->set($this->getTableKey($serviceNode),$info);
        return true;
    }

    function failDown(ServiceNode $serviceNode): bool
    {
        $this->table->set($this->getTableKey($serviceNode),['lastFailTime'=>time()]);
        return true;
    }

    private function getTableKey(ServiceNode $serviceNode)
    {
        return substr(md5($serviceNode->getIp().$serviceNode->getPort().$serviceNode->getService()),8,16);
    }
}