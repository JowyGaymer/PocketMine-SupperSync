<?php
namespace WebLogin;

use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerRespawnEvent;
//use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

use onebone\economyapi\EconomyAPI;

use WebLogin\database\PConfig;


class Main extends PluginBase implements Listener{
	
	private $login,$newplayer,$timertimeout,$Ptimer,$move,$kick,$mode;
	private $pper = array();
	
	public function onLoad(){
		$this->path = $this->getDataFolder();
		@mkdir($this->path);
		@mkdir($this->path."/Players");
		$this->newplayer=$this->path."/Players/";
	}
	
	public function onEnable(){ 
	    //$this->db = new Message($this->path);
		$this->conf = new PConfig($this->path);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$conf=$this->conf->getall();
		$this->getLogger()->info(TextFormat::AQUA."插件读取成功！");
		$this->getLogger()->info(TextFormat::AQUA."API地址： ".$conf['url'].'/'.$conf['api']);
		$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?version=1.2.0');
		$webdata = json_decode($webdata, true);
		if($webdata['name']=='weblogin' and $webdata['version']=='1.2.0'){
			$this->getLogger()->info(TextFormat::AQUA."API连接成功!");
			$this->mode=true;
		}	elseif($webdata['version']!='1.2.0') {
			$this->getLogger()->info(TextFormat::RED.'API与插件版本不符，可能会出现问题');
			$this->mode=true;
		} else {
			$this->getLogger()->info(TextFormat::AQUA."API连接失败，请检查网络");
			$this->mode=false;
		}
	}
	public function onJoin(PlayerJoinEvent $event){
		$this->conf = new PConfig($this->path);
		$conf=$this->conf->getall();
	    $player = $event->getPlayer();
		$user = strtolower($player->getName());
		$id = $player->getName();
		$url = $conf['url'];
		$cid=base64_encode($player->getClientID());
		$ip=$player->getAddress();
		$this->pper[$user]=false;
		//date_default_timezone_set('Asia/Shanghai'); //系统时间差8小时问题
		if(!file_exists($this->newplayer."{$user}.yml")){
			$p = new Config($this->newplayer."{$user}.yml", Config::YAML, array(
				"username"=>$user,
				"cid"=>$cid,
				"ip"=>$ip,
			));
			$p->save();
			unset($p);
		}
		$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?mode=data&username='.urlencode($id));
		$data = json_decode($webdata, true);
		$event->getPlayer()->setNameTag(TextFormat::AQUA.'['.$user.']'.TextFormat::GREEN.$data['name']);
		$this->getServer()->getLogger()->info(TextFormat::GOLD."[玩家]".TextFormat::BLUE.' '.$data['name']." 加入了游戏");
		$event->getPlayer()->sendMessage("[提示] ".TextFormat::GOLD.$data['name']."，欢迎回到服务器");
		EconomyAPI::getInstance()->setMoney($id, intval($data['money']));
	}
	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
		$this->conf = new PConfig($this->path);
		$conf=$this->conf->getall();
	    $player = $event->getPlayer();
		$user = strtolower($player->getName());
		$id = $player->getName();
		$url = $conf['url'];
		$cid=base64_encode($player->getClientID());
		$ip=$player->getAddress();
		$pp = new Config($this->newplayer."$user.yml", Config::YAML);
		$scid=$pp->get("cid");
		$sip=$pp->get("ip");
		if($scid == $cid){ //如果cid匹配直接过
			$this->pper[$user]=true;
			$event->getPlayer()->sendMessage("[提示] ".TextFormat::GOLD."欢迎回到服务器");
		} elseif(preg_match('/^192./',$ip)!=false){ //局域网也通过
			$event->getPlayer()->sendMessage("[提示] ".TextFormat::GOLD."欢迎回到服务器");
		} else { //验证是不是在网站登录了
			$webdata = Utils::getURL($url.'/'.$conf['api'].'?mode=login&username='.urlencode($id).'&ip='.$ip);
			if ($webdata == 'true') {
				$this->pper[$user]=true;
				//保存cid
				$pp->set('cid', $cid);
				$pp->save();
				$event->getPlayer()->sendMessage("[提示] ".TextFormat::GOLD."欢迎回到服务器");
			} else {
				$event->setCancelled(true);
				$player->kick("\n§b请到\n§e§o$url\n§a§r登录或注册");
			}
		}
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->conf = new PConfig($this->path);
		$conf=$this->conf->getall();
	    $player = $event->getPlayer();
		$user = strtolower($player->getName());
		$id = $player->getName();
		$money=EconomyAPI::getInstance()->myMoney($id);
		$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?mode=update&username='.urlencode($id).'&money='.$money);
		if ($webdata=='true'){
			$this->getLogger()->info(TextFormat::AQUA.$id."的金币数据向API提交成功！");
		}
	}
}