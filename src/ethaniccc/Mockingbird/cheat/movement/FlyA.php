<?php

namespace ethaniccc\Mockingbird\cheat\movement;

use ethaniccc\Mockingbird\cheat\Cheat;
use ethaniccc\Mockingbird\cheat\StrictRequirements;
use ethaniccc\Mockingbird\event\MoveEvent;
use ethaniccc\Mockingbird\Mockingbird;
use ethaniccc\Mockingbird\utils\LevelUtils;
use ethaniccc\Mockingbird\utils\MathUtils;
use pocketmine\block\BlockIds;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;

class FlyA extends Cheat implements StrictRequirements{

    /** @var array */
    private $lastDistY = [];
    /** @var array */
    private $previousY = [];

    /** @var array */
    private $lastOnGround = [];
    /** @var array */
    private $lastLastOnGround = [];

    /** @var array */
    private $fallDamageTick = [];
    /** @var array */
    private $hitTick = [];
    /** @var array */
    private $joinTick = [];

    public function __construct(Mockingbird $plugin, string $cheatName, string $cheatType, bool $enabled = true){
        parent::__construct($plugin, $cheatName, $cheatType, $enabled);
        $this->setRequiredTPS(19.5);
        $this->setRequiredPing(20000);
    }

    public function onMove(MoveEvent $event) : void{
        $player = $event->getPlayer();
        $name = $player->getName();
        if($event->getMode() === MoveEvent::MODE_NORMAL){
            if($event->getPlayer()->isFlying() || $event->getPlayer()->getAllowFlight()){
                return;
            }
            if($event->getPlayer()->isCreative()){
                return;
            }
            if($event->getPlayer()->getMotion()->getX() > 0 || $event->getPlayer()->getMotion()->getZ() > 0){
                return;
            }
            $position = $event->getTo();
            $yDiff = $event->getDistanceY();
            if(!isset($this->lastDistY[$name])){
                $this->lastDistY[$name] = $yDiff;
                return;
            }
            $lastYDiff = $this->lastDistY[$name];
            $predictedDiff = ($lastYDiff - 0.08) * 0.980000019073486;
            $onGround = LevelUtils::isNearGround($event->getPlayer());
            if(!isset($this->lastOnGround[$name])){
                $this->lastOnGround[$name] = $onGround;
                return;
            }
            if(!isset($this->lastLastOnGround[$name])){
                $this->lastLastOnGround[$name] = $onGround;
                return;
            }
            $lastOnGround = $this->lastOnGround[$name];
            $lastLastOnGround = $this->lastLastOnGround[$name];

            if(!$onGround && !$lastOnGround && !$lastLastOnGround && abs($predictedDiff) >= 0.005){
                if(!MathUtils::isRoughlyEqual($yDiff, $predictedDiff)){
                    if(!$this->recentlyHit($name) && !$this->recentlyFell($name) && !$this->recentlyJoined($name) && !LevelUtils::isNearBlock($event->getPlayer(), BlockIds::COBWEB, 2)){
                        $this->addPreVL($name);
                        if($this->getPreVL($name) >= 2){
                            $this->addViolation($name);
                            $this->notifyStaff($name, $this->getName(), $this->genericAlertData($event->getPlayer()));
                            $this->lowerPreVL($name, 0);
                        }
                    }
                } else {
                    $this->lowerPreVL($name);
                }
                if($event->onGround()){
                    // Horion makes this value true in it's Jetpack "bypass" mode.
                    // Saving this for another check....
                    $this->debug("$name gave a move packet with the value for onGround true, while not on ground for >= 3 ticks.");
                }
            } else {
                $this->lowerPreVL($name);
            }

            $this->previousY[$name] = $position->getY();
            $this->lastDistY[$name] = $yDiff;
            $this->lastOnGround[$name] = $onGround;
            $this->lastLastOnGround[$name] = $this->lastOnGround[$name];
        }
    }

    public function onDamage(EntityDamageEvent $event) : void{
        $entity = $event->getEntity();
        if($event->getCause() === EntityDamageEvent::CAUSE_FALL){
            if($entity instanceof Player){
                $this->fallDamageTick[$entity->getName()] = $this->getServer()->getTick();
            }
        }
        if($event instanceof EntityDamageByEntityEvent){
            if($entity instanceof Player){
                $this->hitTick[$entity->getName()] = $this->getServer()->getTick();
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event) : void{
        $this->joinTick[$event->getPlayer()->getName()] = $this->getServer()->getTick();
    }

    private function recentlyJoined(string $name) : bool{
        return isset($this->joinTick[$name]) ? $this->getServer()->getTick() - $this->joinTick[$name] <= 60 : true;
    }

    private function recentlyFell(string $name) : bool{
        return isset($this->fallDamageTick[$name]) ? $this->getServer()->getTick() - $this->fallDamageTick[$name] <= 5 : false;
    }

    private function recentlyHit(string $name) : bool{
        return isset($this->hitTick[$name]) ? $this->getServer()->getTick() - $this->hitTick[$name] <= 35 : false;
    }

}