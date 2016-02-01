<?php
namespace AutoGrow\task;

use pocketmine\scheduler\PluginTask;

class DoChunksTickTask extends PluginTask{
	protected $owner;

 	public function onRun($currentTick){
		$this->owner->doChunksTick();
	}
}