<?php
namespace epicmc\bridgeauth;


use epicmc\bridgeauth\task\AuthenticateTask;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class BridgeAuth extends PluginBase implements Listener{
    /** @var  Player[] */
    protected $waitingAuthentication;
    /** @var  Player[] */
    protected $pendingAuthentication;
    protected $localCache;
    const EPICMC_API_URL = "https://api.epicmc.us/bridge";
    const UNSUCCESSFUL_LOGIN = 0;
    const SUCCESSFUL_LOGIN = 1;
    const BRIDGE_TOKEN_NOT_CLAIMED = 2;
    const NOT_REGISTERED = 3;
    const INVALID_ACCESS_TOKEN = 4;
    const TEMPORARILY_THROTTLED = 5;

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->waitingAuthentication = [];
        $this->pendingAuthentication = [];
        $this->localCache = [];
        $this->getLogger()->info("\n-------------------------------------\n\nEPICMC Bridge Auth\nThis server is using the EPICMC bridge authentication system with " . $this->getConfig()->get('access-token') . " as the access token. Your usage of the API will be monitored by EPICMC and will be suspended following malicious use.\n\n-------------------------------------");
    }
    public function onPlayerJoin(PlayerJoinEvent $event){
        if(isset($this->localCache[$event->getPlayer()->getAddress()]) && isset($this->localCache[$event->getPlayer()->getAddress()][$event->getPlayer()->getName()])){
            $event->getPlayer()->sendMessage("Attempting to authenticate you with saved data...");
            $this->pendingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
            $task = new AuthenticateTask($this->getConfig()->get('access-token'), $event->getPlayer()->getName(), $this->localCache[$event->getPlayer()->getAddress()][$event->getPlayer()->getName()]);
            $this->getServer()->getScheduler()->scheduleAsyncTask($task);
        }
        else{
            $this->waitingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
            $event->getPlayer()->sendMessage(TextFormat::DARK_GREEN . "EPICMC" . TextFormat::RESET ." BridgeAuth");
            $event->getPlayer()->sendMessage("> Enter your 14 character bridge_token in the chat.");
            $event->getPlayer()->sendMessage("> You can claim yours at https://epicmc.us/account");
        }
    }
    public function onPlayerChat(PlayerChatEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()])){
            if(strlen($event->getMessage()) === 14) {
                $event->getPlayer()->sendMessage("Attempting to authenticate...");
                $this->pendingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
                unset($this->waitingAuthentication[$event->getPlayer()->getName()]);
                $task = new AuthenticateTask($this->getConfig()->get('access-token'), $event->getPlayer()->getName(), $event->getMessage());
                $this->getServer()->getScheduler()->scheduleAsyncTask($task);
            }
            else{
                $event->getPlayer()->sendMessage("That bridge token isn't valid. Bridge tokens are 14 characters long.");
            }
            $event->setCancelled();
        }
        elseif(isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->getPlayer()->sendMessage("You can't authenticate at this time.");
            $event->setCancelled();
        }
    }
    public function onPlayerMove(PlayerMoveEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()]) || isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->setCancelled();
        }
    }
    public function onPlayerInteract(PlayerInteractEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()]) || isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->setCancelled();
        }
    }
    public function onBlockPlace(BlockPlaceEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()]) || isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->setCancelled();
        }
    }
    public function onBlockBreak(BlockBreakEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()]) || isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->setCancelled();
        }
    }
    public function authComplete($accessToken, $name, $bridgeToken, $result){
        if(isset($this->pendingAuthentication[$name])){
            $player = $this->pendingAuthentication[$name];
            switch($result){
                case BridgeAuth::UNSUCCESSFUL_LOGIN:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage("Bad bridge token. You can try again.");
                    break;
                case BridgeAuth::SUCCESSFUL_LOGIN:
                    unset($this->pendingAuthentication[$player->getName()]);
                    if(!isset($this->localCache[$player->getAddress()])){
                        $this->localCache[$player->getAddress()] = [];
                    }
                    $this->localCache[$player->getAddress()][$player->getName()] = $bridgeToken;
                    $player->sendMessage("Authenticated with EPICMC Bridge API.");
                    break;
                case BridgeAuth::BRIDGE_TOKEN_NOT_CLAIMED:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage("Your account doesn't have a bridge_token associated with it. You can claim yours at https://epicmc.us/account");
                    break;
                case BridgeAuth::NOT_REGISTERED:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage("There isn't account with that username. You can register one at https://epicmc.us/register");
                    break;
                case BridgeAuth::INVALID_ACCESS_TOKEN:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $this->getLogger()->critical("EPICMC access_token is invalid.");
                    $player->sendMessage("This server can't communicate with the Bridge API due to an invalid token. The server admin has been notified.");
                    break;
                case BridgeAuth::TEMPORARILY_THROTTLED:
                    $player->kick("You have made too many login attempts.");
                    break;
            }
        }
        else{
            $this->getLogger()->warning("Extraneous request detected. Result ignored.");
        }
    }
    public function onPlayerQuit(PlayerQuitEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()])){
            unset($this->waitingAuthentication[$event->getPlayer()->getName()]);
        }
        if(isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            unset($this->pendingAuthentication[$event->getPlayer()->getName()]);
        }
    }

}