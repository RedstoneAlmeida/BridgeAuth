<?php
namespace epicmc\bridgeauth\task;

use epicmc\bridgeauth\BridgeAuth;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Utils;

class AuthenticateTask extends AsyncTask{
    protected $accessToken;
    protected $name;
    protected $bridgeToken;
    protected $serverIP;
    protected $serverPort;

    public function __construct($accessToken, $name, $bridgeToken, $serverIP, $serverPort){
        $this->accessToken = $accessToken;
        $this->name = $name;
        $this->bridgeToken = $bridgeToken;
        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort;
    }

    /**
     * Actions to execute when run
     *
     * @return void
     */
    public function onRun(){
        $this->setResult(Utils::getURL(BridgeAuth::EPICMC_API_URL . "/" . $this->accessToken . "/" . $this->serverIP . "/" . $this->serverPort . "/" . $this->name . "/" . $this->bridgeToken));
    }

    public function onCompletion(Server $server){
        $plugin = $server->getPluginManager()->getPlugin("BridgeAuth");
        if($plugin instanceof BridgeAuth && $plugin->isEnabled()){
            $plugin->authComplete($this->accessToken, $this->serverIP, $this->$serverPort, $this->name, $this->bridgeToken, $this->getResult());
        }

    }
}
