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
use pocketmine\level\sound\LaunchSound;
use pocketmine\utils\Config;

class BridgeAuth extends PluginBase implements Listener{
    /** @var  Player[] */
    protected $waitingAuthentication;
    /** @var  Player[] */
    protected $pendingAuthentication;
    protected $localCache;
    /** Don't edit these, or the plugin will be unable to properly interpret the Bridge API */
    const EPICMC_API_URL = "https://api.epicmc.us/bridge";
    const AUTH_FAILED = 0;
    const AUTH_SUCCESS = 1;
    const BRIDGE_TOKEN_NOT_CLAIMED = 2;
    const NOT_REGISTERED = 3;
    const INVALID_ACCESS_TOKEN = 4;
    const AUTH_THROTTLED = 5;

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->waitingAuthentication = [];
        $this->pendingAuthentication = [];
        $this->localCache = [];
    }
    public function onPlayerJoin(PlayerJoinEvent $event){
        
        if(isset($this->localCache[$event->getPlayer()->getAddress()]) && isset($this->localCache[$event->getPlayer()->getAddress()][$event->getPlayer()->getName()])){
            $event->getPlayer()->sendTip(TextFormat::YELLOW . "Welcome back!");
            $event->getPlayer()->sendPopup(TextFormat::GREEN . "Authenticating with saved data...");

            $this->pendingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
            $task = new AuthenticateTask($this->getConfig()->get('access_token'), $this->getConfig()->get('server_ip'), $this->getConfig()->get('server_port'), $event->getPlayer()->getName(), $this->localCache[$event->getPlayer()->getAddress()][$event->getPlayer()->getName()]);
            $this->getServer()->getScheduler()->scheduleAsyncTask($task);
        }
        else{
            $this->waitingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
            $event->getPlayer()->sendMessage(TextFormat::DARK_GREEN ."[EPICMC] " . TextFormat::YELLOW . "This server uses the Bridge API to handle player authentication.\n- You can find your bridge token by logging in online at " . TextFormat::GREEN . "epicmc.us/account" . TextFormat::WHITE . ".\n- Your bridge token is used in place of your EPICMC password when authenticating on servers that use the Bridge API.\n" . TextFormat::GRAY . "> " . TextFormat::ITALIC . "To login enter your bridge token into chat.\n");
        }
    }
    public function onPlayerChat(PlayerChatEvent $event){
        if(isset($this->waitingAuthentication[$event->getPlayer()->getName()])){
            if(strlen($event->getMessage()) === 14) {
$player = $event->getPlayer();
                $event->getPlayer()->sendPopup(TextFormat::GREEN . "Authenticating with bridge token...");

                $this->pendingAuthentication[$event->getPlayer()->getName()] = $event->getPlayer();
                unset($this->waitingAuthentication[$event->getPlayer()->getName()]);
                $task = new AuthenticateTask($this->getConfig()->get('access_token'), $this->getConfig()->get('server_ip'), $this->getConfig()->get('server_port'), $event->getPlayer()->getName(), $event->getMessage());
                $this->getServer()->getScheduler()->scheduleAsyncTask($task);
            }
            else{
                $event->getPlayer()->sendMessage(TextFormat::RED ."Your bridge token is supposed to be a fourteen character string.".TextFormat::WHITE ."\n".TextFormat::ITALIC ."- To login enter your bridge token listed at " . TextFormat::GREEN . "epicmc.us/account" . TextFormat::WHITE . ".");
            }
            $event->setCancelled();
        }
        elseif(isset($this->pendingAuthentication[$event->getPlayer()->getName()])){
            $event->getPlayer()->sendPopup(TextFormat::RED ."Unable to authenticate at this time.");
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
    public function authComplete($accessToken, $serverIP, $serverPort, $name, $bridgeToken, $result){
        if(isset($this->pendingAuthentication[$name])){
            $player = $this->pendingAuthentication[$name];
            switch($result){
                case BridgeAuth::AUTH_FAILED:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage(TextFormat::RED . "The bridge token you entered was incorrect.".TextFormat::WHITE ."\n".TextFormat::ITALIC ."- To reset your bridge token visit " . TextFormat::GREEN . "epicmc.us/account" . TextFormat::WHITE . ". \n- Or exit and choose an available username.");
                    break;
                case BridgeAuth::AUTH_SUCCESS:
                    unset($this->pendingAuthentication[$player->getName()]);
                    if(!isset($this->localCache[$player->getAddress()])){
                        $this->localCache[$player->getAddress()] = [];
                    }
                    $this->localCache[$player->getAddress()][$player->getName()] = $bridgeToken;
                    $player->sendPopup(TextFormat::GREEN . "Authenticated");
                    $launch = new LaunchSound($player);
                    $player->getLevel()->addSound($launch);
                    break;
                case BridgeAuth::BRIDGE_TOKEN_NOT_CLAIMED:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage(TextFormat::RED ."This account hasn't generated a bridge token yet.".TextFormat::WHITE ."\n".TextFormat::ITALIC ."- To generate a bridge token visit " . TextFormat::GREEN . "epicmc.us/account" . TextFormat::WHITE . ". \n- Your bridge token is used in place of your password when logging into the Bridge API.");
                    break;
                case BridgeAuth::NOT_REGISTERED:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $player->sendMessage(TextFormat::YELLOW ."This server uses the EPICMC Bridge API to authenticate its players.\n".TextFormat::ITALIC ."- To register this account visit " . TextFormat::GREEN . "epicmc.us/register" . TextFormat::WHITE . ". \n- After verifying your email you'll be able to generate a bridge token.");
                    break;
                case BridgeAuth::INVALID_ACCESS_TOKEN:
                    $this->waitingAuthentication[$player->getName()] = $player;
                    unset($this->pendingAuthentication[$player->getName()]);
                    $this->getLogger()->critical(TextFormat::RED . "An invalid access token is preventing BridgeAuth from communicating with the Bridge API. You can claim an access token at epicmc.us/account. Afterwards you enter it in this plugin's config.");
                    $player->sendPopup(TextFormat::RED . "Authentication Interrupted");
                    break;
                case BridgeAuth::AUTH_THROTTLED:
                    $player->sendMessage(TextFormat::RED ."This account has failed at authenticating a fair amount of times, and is temporarily throttled. Try again in 30 seconds or so.");
                    $player->sendPopup(TextFormat::RED . "Authentication Throttled");
                    break;
            }
        }
        else{
            $this->getLogger()->warning(TextFormat::RED . "Extraneous request detected. Result ignored.");
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
