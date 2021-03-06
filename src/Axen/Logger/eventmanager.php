<?php

namespace Axen\Logger;

use Axen\Logger\log;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\tile\Chest;
use pocketmine\block\Chest as bc;
use pocketmine\block\TrappedChest;
use pocketmine\block\Furnace;
use pocketmine\item\Air;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\utils\TextFormat as C;

class eventmanager implements Listener {

    private $log , $datebase , $invBlock , $x , $y , $z;

    public function __construct(log $plugin) {
        $this->log = $plugin;
        $this->invBlock = 0;
    }

    public function onJoin(PlayerJoinEvent $event) {
        $name = strtolower($event->getPlayer()->getName());
        $time = $this->log->getTime();
        $date = $this->log->getDate();
        $query = $this->log->getDatabase()->prepare("SELECT player FROM PlayerLog WHERE player='$name' ");
        $result = $query->execute();
        $data = $this->log->fetchall($result);
        if($data == NULL) {
			if($event->getPlayer()->isOp()) {
				$identity = "Admin";
			} else {
				$identity = "Player";
			}
            $this->log->getDatabase()->exec("INSERT INTO PlayerLog (player, identity, join_date, last_join, last_online) VALUES ('$name', '$identity', '$date', '$date', '$time')");
            $this->log->getLogger()->notice("Player '$name' not found! Player is now registered in datebase!");
        } else {
			if($event->getPlayer()->isOp()) {
				$identity = "Admin";
				$this->log->getDatabase()->exec("UPDATE PlayerLog SET identity='$identity' WHERE player='$name'");
			}
		}
    }

    public function onQuit(PlayerQuitEvent $event) {
        $name = strtolower($event->getPlayer()->getName());
        $lastjoin = $this->log->getDate();
        $lastonline = $this->log->getTime();
        $this->log->database->exec("UPDATE PlayerLog SET last_join='$lastjoin', last_online='$lastonline' WHERE player='$name' ");

		//reset the flag from querycommand
		$this->log->updateFlag();
    }

    public function onBreakBlock(BlockBreakEvent $event) {
        $name = strtolower($event->getPlayer()->getName());
        $date = $this->log->getDate();
        $time = $this->log->getTime();
        $block = $event->getBlock();
        $blockId = $block->getId();
        $blockname = $block->getName();
	      $x = $block->getFloorX();
	      $y = $block->getFloorY();
	      $z = $block->getFloorZ();
        $level = $event->getPlayer()->getLevel()->getName();
        $this->log->database->exec("INSERT INTO ServerLog (date, time, player, level, x, y, z, event, objectid) VALUES ('$date', '$time', '$name', '$level', '$x', '$y', '$z', 2, '$blockId')");
    }

    public function onPlaceBlock(BlockPlaceEvent $event) {
        $name = strtolower($event->getPlayer()->getName());
        $date = $this->log->getDate();
        $time = $this->log->getTime();
        $block = $event->getBlock();
        $blockId = $block->getId();
        $blockname = $block->getName();
		$x = $block->getFloorX();
		$y = $block->getFloorY();
		$z = $block->getFloorZ();
        $level = $event->getPlayer()->getLevel()->getName();
        $this->log->database->exec("INSERT INTO ServerLog (date, time, player, level, x, y, z, event, objectid) VALUES ('$date', '$time', '$name', '$level', '$x', '$y', '$z', 1, '$blockId')");
    }

  /**
   *  $pos1 = Block that player touch
   *  $pos2 = Position of the paired chest if it exists
   */
	public function onInteraction(PlayerInteractEvent $event) {
		if($event->getBlock() instanceof bc or Furnace::class or TrappedChest::class) {
    		$this->invBlock = $event->getBlock()->getId();
    		$this->x = $event->getBlock()->getFloorX();
    		$this->y = $event->getBlock()->getFloorY();
    		$this->z = $event->getBlock()->getFloorZ();
		  if($this->log->taptoquery) {
        if(($event->getBlock() instanceof bc) or ($event->getBlock() instanceof TrappedChest)) {
          $tile = $event->getPlayer()->getLevel()->getTile(new \pocketmine\math\Vector3($this->x, $this->y, $this->z));
          if($tile->isPaired()) {
            $pair = $tile->getPair();
            $pos1[0] = $tile->getBlock()->getFloorX();
      		  $pos1[1] = $tile->getBlock()->getFloorY();
      		  $pos1[2] = $tile->getBlock()->getFloorZ();

            $pos2[0] = $pair->getBlock()->getFloorX();
      		  $pos2[1] = $pair->getBlock()->getFloorY();
      		  $pos2[2] = $pair->getBlock()->getFloorZ();
          } else {
                $pos1[0] = $pos2[0] = $event->getBlock()->getFloorX();
                $pos1[1] = $pos2[1] = $event->getBlock()->getFloorY();
                $pos1[2] = $pos2[2] = $event->getBlock()->getFloorZ();
              }
    		} else {
            $pos1[0] = $pos2[0] = $event->getBlock()->getFloorX();
            $pos1[1] = $pos2[1] = $event->getBlock()->getFloorY();
            $pos1[2] = $pos2[2] = $event->getBlock()->getFloorZ();
          }
        $this->log->queryServerLog($event->getPlayer(), $pos1, $pos2, true);
        $event->setCancelled();
      }
    }
  }

	/*
	To do list
	- cancel event, block inventory to block inventory
	- fetch the number that amount of item transferred (different slots)
	*/

	/*
	player to chest: player->chest(block)->chest(block)
	chest to player: chest(block)->player->player(block)
	*/
    public function onTransaction(InventoryTransactionEvent $event) {
        $player = $event->getTransaction()->getSource();
        $viewer = null;
        $playerinv = null;
        $blockinv = null;
	      $ptob = false;
        $btop = false;
        foreach($event->getTransaction()->getInventories() as $inventory) {
            if($inventory->getHolder() instanceof Player) {
				    //echo'player/'; var_dump($inventory->getHolder()->getName());
                $playerinv = $inventory->getHolder();
		            $databaseAction = $this->log->findAction($this->invBlock, true);
	              $btop = true;
            }
            if(($inventory->getHolder() instanceof bc or class_exists(TrappedChest::class) or class_exists(Furnace::class)) and !$inventory->getHolder() instanceof Player) {
	          //echo'block/' ;var_dump($inventory->getHolder()->getName());
                $blockinv = $inventory->getHolder();
		            $databaseAction = $this->log->findAction($this->invBlock, false);
                $ptob = true;
            }
        		if($inventory->getHolder() instanceof Air) {
        			continue;
        		}
        }
        if(isset($playerinv) and isset($blockinv) and $playerinv != $blockinv) {
            $trActions = $event->getTransaction()->getActions();
            foreach($trActions as $action) {
                if($action->getSourceItem()->getId() == 0) {
                    continue;
                } else {
			              $objectid = $action->getSourceItem()->getId();
            				$amount = $action->getSourceItem()->getCount();

            				$name = strtolower($player->getName());
            				$date = $this->log->getDate();
            				$time = $this->log->getTime();

            				$level = $player->getLevel()->getName();
                    $this->log->database->exec("INSERT INTO ServerLog (date, time, player, level, x, y, z, event, objectid, amount) VALUES ('$date', '$time', '$name', '$level', '$this->x', '$this->y', '$this->z', '$databaseAction', '$objectid', $amount)");
                }
            }
        }
    }

}
