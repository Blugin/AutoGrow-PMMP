<?php

namespace AutoGrow;

use pocketmine\plugin\PluginBase;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\level\format\FullChunk;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat as Color;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\server\RemoteServerCommandEvent;
use AutoGrow\task\DoChunksTickTask;

class AutoGrow extends PluginBase implements Listener{ 
	const NETHER_WART_BLOCK = 115;
	const NETHER_WART_SEEDS = 372;
	const COCOA_BEANS_BLOCK = 127;

	public $levelTickBlocks = [], $randomTickBlocks = [], $randomTickBlocksProperty, $chunksPerTickProperty, $maxDamage = [
	 	Block::WHEAT_BLOCK => 0x07,
		Block::BEETROOT_BLOCK => 0x07,
		Block::CARROT_BLOCK => 0x07,
		Block::POTATO_BLOCK => 0x07,
	 	self::COCOA_BEANS_BLOCK => 0xA1,
		self::NETHER_WART_BLOCK => 0x04
	];

	public function onLoad(){
		$reflectionLevel = new \ReflectionClass(Level::class);
		$this->randomTickBlocksProperty = $reflectionLevel->getProperty("randomTickBlocks");
		$this->randomTickBlocksProperty->setAccessible(true);
		$this->chunksPerTickProperty = $reflectionLevel->getProperty("chunksPerTick");
		$this->chunksPerTickProperty->setAccessible(true);
	}

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new DoChunksTickTask($this), 200);
	}

	public function onDisable(){
		$this->saveData();
	}

	/**
	 * @priority HIGHEST
	 */
	public function onBlockPlace(BlockPlaceEvent $event){
		if(!$event->isCancelled()){
			$block = $event->getBlock();
			if(isset($this->levelTickBlocks[$levelName = $block->getLevel()->getFolderName()]) && isset($this->randomTickBlocks[$levelName])){
				if(in_array($block->getID(), $this->randomTickBlocks[$levelName]) && $block->getID() !== Block::GLASS && !isset($this->levelTickBlocks[$levelName][$posKey = $block->x . ":" . $block->y . ":" . $block->z])){
					$this->levelTickBlocks[$levelName][$posKey] = new Position($block->x, $block->y, $block->z, $block->level);
				}
			}
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onBlockUpdate(BlockUpdateEvent $event){
		if(!$event->isCancelled()){
			$block = $event->getBlock();
			if(isset($this->levelTickBlocks[$levelName = $block->getLevel()->getFolderName()]) && isset($this->randomTickBlocks[$levelName])){
				if(in_array($block->getID(), $this->randomTickBlocks[$levelName]) && $block->getID() !== Block::GLASS && !isset($this->levelTickBlocks[$levelName][$posKey = $block->x . ":" . $block->y . ":" . $block->z])){
					$this->levelTickBlocks[$levelName][$posKey] = new Position($block->x, $block->y, $block->z, $block->level);
				}
			}
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$block = $event->getBlock();
		if(isset($this->levelTickBlocks[$levelName = $block->getLevel()->getFolderName()]) && isset($this->randomTickBlocks[$levelName])){
			if(in_array($block->getID(), $this->randomTickBlocks[$levelName]) && $block->getID() !== Block::GLASS && !isset($this->levelTickBlocks[$levelName][$posKey = $block->x . ":" . $block->y . ":" . $block->z])){
				$this->levelTickBlocks[$levelName][$posKey] = new Position($block->x, $block->y, $block->z, $block->level);
			}
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onServerCommandProcess(ServerCommandEvent $event){
		if(!$event->isCancelled() && stripos("save-all", $command = $event->getCommand()) === 0){
			$this->checkSaveAll($event->getSender());
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onRemoteServerCommand(RemoteServerCommandEvent $event){
		if(!$event->isCancelled() && stripos("save-all", $command = $event->getCommand()) === 0){
			$this->checkSaveAll($event->getSender());
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		if(!$event->isCancelled() && stripos("/save-all", $command = $event->getMessage()) === 0){
			$this->checkSaveAll($event->getPlayer());
		}
	}

	public function checkSaveAll(CommandSender $sender){
		if(($command =  $this->getServer()->getCommandMap()->getCommand("save-all")) instanceof Command && $command->testPermissionSilent($sender)){
			$this->saveData();
			$sender->sendMessage(Color::YELLOW . "[AutoGrow] Saved levelTickBlocks.");
		}
	}

	public function doChunksTick(){
		foreach($this->levelTickBlocks as $levelName => $blocks){
			if(isset($this->randomTickBlocks[$levelName])){
				$keys = array_keys($blocks);
				for($i = mt_rand(0, 20); $i < count($keys); $i += mt_rand(1, 20)){
					$pos = $blocks[$keys[$i]];
					$chunk = $pos->level->getChunk($pos->x >> 4, $pos->z >> 4, true);
					if($chunk instanceof FullChunk){
						if(!$chunk->isGenerated()){
							$chunk->setGenerated(true);
						}
						if(!$chunk->isPopulated()){
							$chunk->setPopulated(true);
						}
					}
		 			$block = $pos->getLevel()->getBlock($pos);
					if(!in_array($id = $block->getID(), $this->randomTickBlocks[$levelName]) || 
						$id === Block::GLASS || 
						isset($this->maxDamage[$id]) && $block->getDamage() >= $this->maxDamage[$id]
					){
						unset($this->levelTickBlocks[$levelName][$keys[$i]]);
					}else{
 						$block->onUpdate(Level::BLOCK_UPDATE_RANDOM);
						if(isset($this->maxDamage[$id]) && $block->getDamage() >= $this->maxDamage[$id]){
							unset($this->levelTickBlocks[$levelName][$keys[$i]]);
						}
					}
				}
			}
		}
		foreach($this->getServer()->getLevels() as $level){
			$this->loadData($level);
		}
	}

	public function loadData(Level $level){
		if(!isset($this->randomTickBlocks[$levelName = $level->getFolderName()])){
			$this->chunksPerTickProperty->setValue($level, -1);
			$this->randomTickBlocks[$levelName] = array_keys($this->randomTickBlocksProperty->getValue($level));
			@mkdir($folder = $level->getProvider()->getPath());
			if(!file_exists($path = $folder . "AutoGrowBlocks.sl")){	
				file_put_contents($path, serialize([]));
			}
			$blocks = [];
			foreach(unserialize(file_get_contents($path)) as $posKey){
				$pos = explode(":", $posKey);
				$blocks[$posKey] = new Position($pos[0], $pos[1], $pos[2], $level);
			}
			$this->levelTickBlocks[$levelName] = $blocks;
		}
	}

	public function saveData(){
		foreach($this->getServer()->getLevels() as $level){
			if(isset($this->levelTickBlocks[$levelName = $level->getFolderName()])){
				@mkdir($folder = $level->getProvider()->getPath());
				$blocks = [];
				foreach($this->levelTickBlocks[$levelName] as $key => $pos){
					$blocks[] = $pos->x . ":" . $pos->y . ":" . $pos->z;
				}
				file_put_contents($folder . "AutoGrowBlocks.sl", serialize($blocks));
			}
		}
	}
}