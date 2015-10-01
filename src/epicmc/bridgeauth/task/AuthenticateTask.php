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

    public function __construct($accessToken, $name, $bridgeToken){
        $this->accessToken = $accessToken;
        $this->name = $name;
        $this->bridgeToken = $bridgeToken;
    }

    /**
     * Actions to execute when run
     *
     * @return void
     */
    public function onRun(){
        $this->setResult(Utils::getURL(BridgeAuth::EPICMC_API_URL . "?access_token=" . $this->accessToken . "&player_name=" . $this->name . "&bridge_token=" . $this->bridgeToken));
    }

    public function onCompletion(Server $server){
        $plugin = $server->getPluginManager()->getPlugin("BridgeAuth");
        if($plugin instanceof BridgeAuth && $plugin->isEnabled()){
            $plugin->authComplete($this->accessToken, $this->name, $this->bridgeToken, $this->getResult());
        }

    }
}