<?php

declare(strict_types = 1);

namespace WarpUI;

use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;

abstract class Form {

	public $id;
	private $data = [];
	public $playerName;
	private $callable;

	public function __construct(int $id, callable $callable) {
		$this->id = $id;
		$this->callable = $callable;
	}

	public function getId() : int {
		return $this->id;
	}

	public function sendToPlayer(Player $player){
		$pk = new ModalFormRequestPacket();
		$pk->formId = $this->id;
		$pk->formData = json_encode($this->data);
		$player->dataPacket($pk);
		$this->playerName = $player->getName();
	}

	public function isRecipient(Player $player) : bool {
		return $player->getName() === $this->playerName;
	}

	public function getCallable() : callable {
		return $this->callable;
	}

}
