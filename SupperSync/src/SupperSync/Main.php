<?php
//本程序由chs制作，小学生请手下留情
//github项目地址：https://github.com/13358172372/PocketMine-SupperSync
//demo站：http://huishao.iego.net
//MC技术联盟论坛：http://mcleague.xicp.net

namespace SupperSync;

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

use SupperSync\database\PConfig;


class Main extends PluginBase implements Listener{
	
	private $login,$newplayer,$timertimeout,$Ptimer,$move,$kick,$mode,$url;
	private $pper = array(),$sendmsg = array();
	
	public function onLoad(){
		$this->path = $this->getDataFolder();
		@mkdir($this->path);
		@mkdir($this->path."/Players");
		$this->newplayer=$this->path."/Players/";
	}
	
	public function onEnable(){ 
	    //$this->db = new Message($this->path);
		$this->conf = new PConfig($this->path);
		$conf = $this->conf->getall();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::AQUA."插件读取成功！");
		$this->getLogger()->info(TextFormat::AQUA."API地址： ".$conf['url'].'/'.$conf['api']);
		$this->url = $conf['url'];
		$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?version=2.1.0');
		$webdata = json_decode($webdata, true);
		if($webdata['name']=='weblogin' and $webdata['version']=='2.1.0'){
			$this->getLogger()->info(TextFormat::AQUA."API连接成功!");
			$this->mode=true;
		}	elseif($webdata['version']!='2.1.0') {
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
		$pp = new Config($this->newplayer.$user.'.yml', Config::YAML);
		$scid=$pp->get("cid");
		$sip=$pp->get("ip");
		$ip=$player->getAddress();
		$this->pper[$user]='off';
		$this->move[$user]=0;
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
		if($scid == $cid){ //如果cid匹配直接过
			$this->pper[$user]='on';
			//echo "cid匹配";
		} elseif(preg_match('/^192./',$ip)!=false){ //局域网也通过
			$this->pper[$user]='on';
		} else { //验证是不是在网站登录了
			$webdata = Utils::getURL($url.'/'.$conf['api'].'?mode=login&username='.urlencode($id).'&ip='.$ip);
			if ($webdata == 'true') {
				$this->pper[$user]='on';
				//保存cid
				$pp->set('cid', $cid);
				$pp->save();
			} else {
			}
		}
		if($this->pper[$user]=='on' and !isset($this->sendmsg[$user])){
			$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?mode=data&username='.urlencode($id));
			$ipdata = utils::getURL('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip='.$ip);
			$data = json_decode($webdata, true);
			$ipdata = json_decode($ipdata, true);
			$event->getPlayer()->setNameTag(TextFormat::GREEN.$data['name'].TextFormat::AQUA.'['.$id.']'."\n");
			if(empty($ipdata['country'])){
				$ipmsg='本地网络';
			} else {
				$ipmsg = $ipdata['country'].'  '.$ipdata['province'].'省 '.$ipdata['city'].'市';
			}
			foreach ($this->getServer()->getOnlinePlayers() as $play){
				$play->sendMessage(TextFormat::GOLD."[提示]".TextFormat::GREEN.'来自 '.$ipmsg.TextFormat::AQUA.' 的 ['.TextFormat::YELLOW.$data['name'].TextFormat::AQUA."] 加入了游戏");
			}
			$this->getServer()->getLogger()->info(TextFormat::GOLD."[提示]".TextFormat::GREEN.'来自 '.$ipmsg.TextFormat::AQUA.' 的 ['.TextFormat::YELLOW.$data['name'].TextFormat::AQUA."] 加入了游戏");
			$event->getPlayer()->sendMessage("[提示] ".TextFormat::GOLD.$data['name']."，欢迎回到服务器");
			EconomyAPI::getInstance()->setMoney($user, intval($data['money']));
			$this->sendmsg[$user]='true';
		} else {
			$event->getPlayer()->sendMessage("§b您还没有登录，请到\n§e§o".$this->url."\n§a§r登录后再进入服务器。");
		}
	}
	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
		//留空
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->conf = new PConfig($this->path);
		$conf=$this->conf->getall();
	    $player = $event->getPlayer();
		$user = strtolower($player->getName());
		$id = $player->getName();
		if($this->pper[$user]=='on'){
			$money=intval(EconomyAPI::getInstance()->myMoney($user));
			$webdata = Utils::getURL($conf['url'].'/'.$conf['api'].'?mode=update&username='.urlencode($id).'&money='.$money);
			if ($webdata=='true'){
				$this->getLogger()->info(TextFormat::AQUA.$id."的金币数据向API提交成功！");
			}
		}
		unset($this->pper[$user]);
		unset($this->timertimeout[$user]);
		unset($this->Ptimer[$user]);
		unset($this->sendmsg[$user]);
	}
	public function onPlayerInteract(PlayerInteractEvent $event){
	    $this->permission($event);
	}		
	public function onBlockBreak(BlockBreakEvent $event){
		$this->permission($event);
	}	
	public function onEntityDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player){
			$user  = strtolower($event->getEntity()->getName());
			if(isset($this->pper[$user]) === false){
				$this->pper[$user] = "off";
				}
		    if($this->pper[$user] == "off" ){
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage("§b您还没有登录，请到\n§e§o".$this->url."\n§a§r登录后再进入服务器。");
			}
		}
	}
	public function onBlockPlace(BlockPlaceEvent $event){
		$this->permission($event);
	}
	public function onPlayerDrop(PlayerDropItemEvent $event){
		$this->permission($event);
	}
	public function onInventoryOpen(InventoryOpenEvent $event){
		$this->permission($event);
	}
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
		$this->permission($event);
	}
	public function onPlayerMove(PlayerMoveEvent $event){
	    $user = strtolower($event->getPlayer()->getName());
		if(isset($this->pper[$user]) === false){
			$this->pper[$user]="off";
		}
		if($this->pper[$user] == "off" ){
			$this->move[$user]++;
			if($this->move[$user] >= 2){
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage("§b您还没有登录，请到\n§e§o".$this->url."\n§a§r登录后再进入服务器。");
				$event->getPlayer()->onGround = true;
			}
		}
		unset($user);
	}
	public function onPickupItem(InventoryPickupItemEvent $event){
		$player = $event->getInventory()->getHolder();
		$user = strtolower($player->getName());
		if(!isset($this->pper[$user])){$this->pper[$user]=="off";}
		if($this->pper[$user] == "off" ){
			$event->setCancelled(true);
			}
	}
	public function permission($event){
	    $user = strtolower($event->getPlayer()->getName());		
		if(isset($this->pper[$user]) === false){
			$this->pper[$user]="off";
		}
		if($this->pper[$user] == "off" ){
			$event->setCancelled(true);
		}
		unset($user);
	}
	public function onPlayerRespwan(PlayerRespawnEvent $event){
		$player = $event->getPlayer();
		$world = $player->getLevel()->getName();
		$user = $player->getName();
		$y = (int)$player->getY();
		if($y <= 1){
			$x = $player->getX();
		    $z = $player->getZ();
			$spawn = $this->getServer()->getLevelByName($world)->getSpawn();			
			$event->setRespawnPosition($spawn);
			$this->getServer()->getLogger()->info(TextFormat::YELLOW."$user ".TextFormat::BLUE."卡虚空修复完成");	
		    unset($x,$z,$spawn);
		}
		unset($player,$world,$user,$y);
	}
}