<?php

namespace WarpUI;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\level;
use pocketmine\level\Position;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class Main extends PluginBase implements Listener {

	public $formCount = 0;
	public $forms = [];

    public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->db = new \SQLite3($this->getDataFolder() . "warps.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS warps(warpname TEXT PRIMARY KEY, x INT, y INT, z INT, world TEXT, image TEXT);");
		$this->config = (new Config($this->getDataFolder() . "message.yml", Config::YAML, array(
		"Title" => "Warp List",
		"Content" => "§7Choose, where you want to teleport?",
		"Teleported" => "§aYou have been teleported!",
		"Warp-Add" => "§athe warp has been created!",
		"Warp-Delete" => "§aThis warp has been deleted!",
		"Warp-Exist" => "§cThis warp already exists!",
		"Warp-Not-Exist" => "§cThis warp does not exist!",
		"No-Warp" => "§cNo warp set!"
		)))->getAll();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label,array $args) : bool {
		
		switch($cmd->getName()){
		
			case "warp":
				if($sender instanceof Player) {
					$form = $this->createSimpleForm(function (Player $sender, array $data){
					$result = $data[0];
					if($result === null){
						return true;
					}
						$warp = $this->db->query("SELECT * FROM warps;");
						$i = -1;
						while ($resultArr = $warp->fetchArray(SQLITE3_ASSOC)) {
							$j = $i + 1;
							$warpname = $resultArr['warpname'];
							$i = $i + 1;
							if($result == $j){
								$warp = $this->db->query("SELECT * FROM warps WHERE warpname = '$warpname';");
								$array = $warp->fetchArray(SQLITE3_ASSOC);
								if (!empty($array)) {
									$sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->getServer()->getLevelByName($array['world'])));
									$sender->sendMessage($this->config["Teleported"]);
								}
							}
						}
					});
					$result = $this->db->query("SELECT * FROM warps;");
					$array = $result->fetchArray(SQLITE3_ASSOC);	
					if (empty($array)) {
						$sender->sendMessage($this->config["No-Warp"]);
						return true;
					}
					$form->setTitle($this->config["Title"]);
					$form->setContent($this->config["Content"]);
					$result = $this->db->query("SELECT * FROM warps;");
					$i = -1;
					while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
						$j = $i + 1;
						$warpname = $resultArr['warpname'];
						$image = $resultArr['image'];
						$form->addButton(TextFormat::BOLD . "$warpname", 1, "$image");
						$i = $i + 1;
					}
					$form->sendToPlayer($sender);
				}
				else{
					$sender->sendMessage(TextFormat::WHITE . "This command can't be used here sorry.");
					return true;
				}
			break;
			
			case "addwarp":
				if($sender instanceof Player) {
					if($sender->hasPermission("add.warpui")){
						if (empty($args)) {
							$sender->sendMessage(TextFormat::GREEN . "Usage: /addwarp <name>");
							return true;
						}
						$warpname = $args[0];
						if (empty($args[1])) {
							$image = "NoImage";
						} else {
							$image = $args[1];
						}
						$warp = $this->db->query("SELECT * FROM warps WHERE warpname = '$warpname';");
						$array = $warp->fetchArray(SQLITE3_ASSOC);
						if (!empty($array)) {
							$sender->sendMessage($this->config["Warp-Exist"]);
							return true;
						}
						$warpdb = $this->db->prepare("INSERT OR REPLACE INTO warps (warpname, x, y, z, world, image) VALUES (:warpname, :x, :y, :z, :world, :image);");
						$warpdb->bindValue(":warpname", $warpname);
						$warpdb->bindValue(":x", $sender->getX());
						$warpdb->bindValue(":y", $sender->getY());
						$warpdb->bindValue(":z", $sender->getZ());
						$warpdb->bindValue(":world", $sender->getPlayer()->getLevel()->getName());
						$warpdb->bindValue(":image", $image);
						$result = $warpdb->execute();
						$sender->sendMessage($this->config["Warp-Add"]);
					}
				}
				else{
					$sender->sendMessage(TextFormat::WHITE . "This command can't be used here.");
					return true;
				}
			break;;
			
			case "delwarp":
				if($sender instanceof Player) {
					if($sender->hasPermission("delete.warpui")){
						if (empty($args)) {
							$sender->sendMessage(TextFormat::GREEN . "Usage: /delwarp <name>");
							return true;
						}
						$warpname = $args[0];
						$warp = $this->db->query("SELECT * FROM warps WHERE warpname = '$warpname';");
						$array = $warp->fetchArray(SQLITE3_ASSOC);
						if (!empty($array)) {
							$this->db->query("DELETE FROM warps WHERE warpname = '$warpname';");
							$sender->sendMessage($this->config["Warp-Delete"]);
						} else {
							$sender->sendMessage($this->config["Warp-Not-Exist"]);
						}
					}
				}
				else{
					$sender->sendMessage(TextFormat::WHITE . "This command can't be used here.");
					return true;
				}
			break;
		}
		return true;
    }
	
	public function createSimpleForm(callable $function = null) : SimpleForm {
		$this->formCount++;
		$form = new SimpleForm($this->formCount, $function);
		if($function !== null){
			$this->forms[$this->formCount] = $form;
		}
		return $form;
	}
	
	public function onPacketReceived(DataPacketReceiveEvent $ev){
		$pk = $ev->getPacket();
		if($pk instanceof ModalFormResponsePacket){
			$player = $ev->getPlayer();
			$formId = $pk->formId;
			$data = json_decode($pk->formData, true);
			if(isset($this->forms[$formId])){
				$form = $this->forms[$formId];
				if(!$form->isRecipient($player)){
					return;
				}
				$callable = $form->getCallable();
				if(!is_array($data)){
					$data = [$data];
				}
				if($callable !== null) {
					$callable($ev->getPlayer(), $data);
				}
				unset($this->forms[$formId]);
				$ev->setCancelled();
			}
		}
	}
	
	public function onPlayerQuit(PlayerQuitEvent $ev){
		$player = $ev->getPlayer();

		foreach($this->forms as $id => $form){
			if($form->isRecipient($player)){
				unset($this->forms[$id]);
				break;
			}
		}
	}

	
}