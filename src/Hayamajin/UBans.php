<?php

namespace Hayamajin;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

use pocketmine\Server;
use pocketmine\Player;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

class UBans extends PluginBase implements Listener{

const VERSION        = "5.2.1";
const CONFIG_VERSION = "0.2";

const UBANS_COMMANDS = <<<COMMANDS
§b/ubans about   §f: §6UBansがどのようなプラグインなのかを確認出来ます
§b/ubans bans    §f: §6UBanされているプレイヤーリストを表示します
§b/ubans warns    §f: §6Warnされているプレイヤーリストを表示します
COMMANDS;

const UBANS_ABOUT    = <<<ABOUT
§eUBans§fとは、§9Hayamajin§fがまったりのんびり開発しているところにXxawarenessxXが邪魔した
§b荒らし対策プラグイン§fです。
今現在では、
・§eUBan(名前、IPアドレス、ホスト、クライアントID(ユニークID)の
 §e同時Banが出来る機能)
・§eWarn(危険プレイヤーを把握出来るように＆
§e 危険プレイヤーのブロックの設置/破壊を制限
という機能が実装されています。
ABOUT;


	public function onEnable(){
        
        date_default_timezone_set("Asia/Tokyo");

		if (!file_exists($this->getDataFolder()))
            mkdir($this->getDataFolder(), 0744, true);

        $this->s        = Server::getInstance();
		$db             = $this->getDataFolder() . "UserData.db";
        $this->BP       = new Config($this->getDataFolder() . "Banned_Players.yml", Config::YAML);
        $this->BL       = new Config($this->getDataFolder() . "Playersb.yml", Config::YAML);
        $this->WP       = new Config($this->getDataFolder() . "Warnned_Players.yml", Config::YAML);
        $this->WL       = new Config($this->getDataFolder() . "Playersw.yml", Config::YAML);
        $this->Setting  = new Config($this->getDataFolder() . "Setting.yml", Config::YAML, array(
                                    "コンフィグバージョン(編集禁止)"                   => self::CONFIG_VERSION,
                                    "参加時にプレイヤーの情報を表示する(true or false)" => "false",
                                    "UBan時にプレイヤーの情報を表示する(true or false)" => "false",
				    "Warnされているプレイヤーのブロックの操作を制限する" => "false"));
        $this->s->getPluginManager()->registerEvents($this, $this);

		if (!file_exists($db)){

			$this->db     = new \SQLite3($db, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

                }else{

                $this->db = new \SQLite3($db, SQLITE3_OPEN_READWRITE);

                }

                $this->DB("CREATE TABLE IF NOT EXISTS player (name TEXT PRIMARY KEY, ip TEXT, host TEXT, cid TEXT, uuid TEXT, rawid TEXT, ban_reason TEXT, warn_reason TEXT, ban INT, warn INT, ban_sender TEXT, warn_sender TEXT, ban_time TEXT, warn_time TEXT)");

                if ($this->getSetting("コンフィグバージョン(編集禁止)") < self::CONFIG_VERSION){

                    $this->getLogger()->warning("§cコンフィグバージョン" .self::CONFIG_VERSION. "は古いです。");
                    $this->getLogger()->warning("§c古いコンフィグ(Setting.yml)を消去して、新しいコンフィグを生成してください");
        }

        $this->getLogger()->info("§eUBans v" . self::VERSION . "§aをロードしました §9by Hayamajin & PawarenessC");

    }

    public function onDisable(){

        $this->getLogger()->info("§7UBans v" . self::VERSION . "を終了しています...");
        $this->db->close();
    }

    public function onPreLogin(PlayerPreLoginEvent $event){

        $player = $event->getPlayer();
        $pd     = $this->getOnlinePlayerData($player);
        $name   = $pd["name"];
        $ip     = $pd["ip"];
        $host   = $pd["host"];
        $cid    = $pd["cid"];
        $uuid   = $pd["uuid"];
        $rawid  = $pd["rawid"];
        $data   = $this->getPlayerData($name);

        if (!$this->isRegistered($name)){

        $this->Register($name, $ip, $host, $cid, $uuid, $rawid);

        } else {

        $this->Update($name, $ip, $host, $cid, $uuid, $rawid);
        }
        if ($this->isUBanned_A($name, $ip, $host, $cid, $uuid, $rawid)){
            $reason = $data["ban_reason"];

            if ($data["ban"] == 0 or empty($data["ban"]) ){
                $reason = "UBanされたプレイヤーのサブアカウント";    
            }
            $sender_name = "UBans (Plugin)";
            /*
             *対策案を考えないと... 
             * $this->UBanByText($name, $ip, $host, $cid, $uuid, $rawid, $reason, $sender_name); 
             */
            $event->setkickMessage("§cあなたは接続禁止状態です \n§e理由 §f: §6$reason");
            $event->setCancelled();
        }

        }
    public function onJoin(PlayerJoinEvent $event){

        $player = $event->getPlayer();
        $pd     = $this->getOnlinePlayerData($player);
        $name   = $pd["name"];
        $ip     = $pd["ip"];
        $host   = $pd["host"];
        $cid    = $pd["cid"];
        $uuid   = $pd["uuid"];
        $rawid  = $pd["rawid"];

        if ($this->isWarned($name) == 1){

                $this->setDanger($player);
            }

        if ($this->getSetting("参加時にプレイヤーの情報を表示する(true or false)") == "true"){

        $this->getLogger()->info("§a名前         §f: §b$name");
        $this->getLogger()->info("§aIPアドレス    §f: §b$ip");
        $this->getLogger()->info("§aホスト        §f: §b$host");
        $this->getLogger()->info("§aクライアントID §f: §b$cid");
        $this->getLogger()->info("§aユニークID    §f: §b$uuid");
        $this->getLogger()->info("§aローID    §f: §b$rawid");

        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool {
        $prefix      = "§e[UBans]§f";
        $sender_name = $sender->getName();

        if ($command->getName() === "uban"){

            if (empty($args[0])){
                //$sender->sendMessage("$prefix §b使い方 : /uban <プレイヤーネーム> <理由>");
		$this->ubanui($sender);
                return true;
            }

            $name   = $args[0];
            $data   = $this->getPlayerData($name);

            $ip     = $data["ip"];
            $host   = $data["host"];
            $cid    = $data["cid"];
            $uuid   = $data["uuid"];
            $rawid  = $data["rawid"];

            if (!$sender instanceof Player){
                    $sender_name = "管理者";
                }
            if (!$this->isRegistered($name)){
                $sender->sendMessage("$prefix §a{$name}§fはサーバーに来ていません");
                return true;
                }

            if ($data["ban"] === 1){
                $sender->sendMessage("$prefix §a{$name}§fは既にUBanされています");
                return true;
                }

            $reason = empty($args[1]) ? "未記入" : $args[1];

            $this->UBan($name, $reason, $sender_name);

            $player = $this->s->getPlayer($name);

            if ($player instanceof Player){
                $player->kick("§cサーバーとの接続が切断されました \n§6理由 §f: §6$reason ", false);
            }
            $this->Broadcast("§a{$sender_name}§fが§c{$name}§fを§eUBan§fしました\n".
                      "$prefix §e理由 §f:§6 $reason");

            $sender->sendMessage("$prefix §c{$name}§fを§eUBan§fしました");

            if ($this->getSetting("UBan時にプレイヤーの情報を表示する(true or false)") == "true"){

                    $this->Broadcast("§aIPアドレス    §f: §b$ip");
                    $this->Broadcast("§aホスト        §f: §b$host");
                    $this->Broadcast("§aクライアントID §f: §b$cid");
                    $this->Broadcast("§aユニークID     §f: §b$uuid");
                    $this->Broadcast("§aローID §f : §b$rawid");
            }
        }
        if ($command->getName() === "unuban"){

            if (empty($args[0])){
                //$sender->sendMessage("$prefix §b使い方 : /unuban <プレイヤーネーム>");
		$this->unubanui($sender);
                return true;
            }

            $name = $args[0];
            $data = $this->getPlayerData($name);

            if (!$this->isRegistered($name)){
                $sender->sendMessage("$prefix §a{$name}§fはサーバーに来ていません");
                return true;
            }
            if ($data["ban"] === 0){
                $sender->sendMessage("$prefix §a{$name}§fはUBanされていません");
                return true;
                }

            $this->unUBan($name);
            $sender->sendMessage("$prefix §a{$name}§fの§eUBan§fを解除しました");
        }

        if ($command->getName() === "warn"){

            if (empty($args[0])){
                //$sender->sendMessage("$prefix §b使い方 : /warn <プレイヤーネーム> <理由>");
		$this->warnui($sender);
                return true;
            }

            $name = $args[0];
            $data = $this->getPlayerData($name);

             if (!$sender instanceof Player){
                    $sender_name = "管理者";
                }

            if (empty($data)){
                $sender->sendMessage("{$prefix} §a{$name}§fさんはサーバーに来ていません");
                return true;
            }
            if ($data["warn"] === 1){
                $sender->sendMessage("$prefix §a{$name}§fは既にWarnされています");
                return true;
            }
            $name = $args[0];

            $reason = empty($args[1]) ? "未記入" : $args[1];

            $this->Warn($name, $reason, $sender_name);
            $player = $this->s->getPlayer($name);

            if ($player instanceof Player){

                $this->setDanger($player);
                $player->sendMessage("$prefix §cあなたは管理者から危険人物に認定されました\n$prefix §c理由 §f:§6$reason");
            }

            $this->Broadcast("§a{$sender_name}§fが§c{$name}§fを§eWarn§fしました\n".
                           "$prefix §e理由 §f:§6 $reason");
            $sender->sendMessage("$prefix §c{$name}§fを§eWarn§fしました");


        }

        if ($command->getName() == "unwarn"){

            if (empty($args[0])){
                //$sender->sendMessage("$prefix §b使い方 : /unwarn <プレイヤーネーム>");
		$this->unwarnui($sender);
                return true;
            }


            $name = $args[0];
            $data = $this->getPlayerData($name);

            if (!$this->isRegistered($name)){
                $sender->sendMessage("$prefix §a{$name}§fはサーバーに来ていません");
                return true;
            }
            if ($data["warn"] === 0){
                $sender->sendMessage("$prefix §a{$name}§fはWarnされていません");
                return true;
                }

            $this->unWarn($name);
            $sender->sendMessage("$prefix §a{$name}§fの§cWarn§fを解除しました");

            $player = $this->s->getPlayer($name);
            if ($player instanceof Player){
            $this->setDefaultStatus($player);
            }

        }
    if ($command->getName() == "ubans"){
        if (empty($args[0])){
        $sender->sendMessage(self::UBANS_COMMANDS);
        return true;
        }
        switch ($args[0]){
        case "about":
        case "a";
        
        $sender->sendMessage(self::UBANS_ABOUT);

        if (!$sender instanceof Player){
        $sender->sendMessage("§b/ubans reload §f: §6コンフィグファイルをリロードします\n".
                             "§c※一部のソフトウェアではリロードしても意味がありません");
        }
        break;

        case "bans":
        case "ubans":
        case "ban":
        case "b":
        $sender->sendMessage("§b--- §eこのサーバーでUBanされたプレイヤー一覧§b---\n".
                             "(" . count($this->BP->getAll()) . ")");
        foreach ($this->BP->getAll() as $a => $bp){
                        
                 $this->uban[$a] = $a;
                        
            }

        @$players = implode(", ", $this->uban);
        $sender->sendMessage("§a$players\n".
                             "§b/ubans info 名前 で詳しい情報を取得出来ます\n".
                             "§b--- §eこのサーバーでUBanされたプレイヤー一覧§b---");
        break;

        case "warns":
        case "warn":
        case "w":

        $sender->sendMessage("§b--- §eこのサーバーでWarnされたプレイヤー一覧§b---\n".
                             "(" . count($this->WP->getAll()) . ")");

        foreach ($this->WP->getAll() as $a => $wp){
                        
                 $this->warn[$a] = $a;
                        
            }
        @$players = implode(", ", $this->warn);
                    
        $sender->sendMessage("§a$players\n".
                             "§b/ubans info 名前 で詳しい情報を取得出来ます\n".
                             "§b--- §eこのサーバーでWarnされたプレイヤー一覧§b---");
        break;

        case "info":
        case "i";
        if (empty($args[1])){
            $sender->sendMessage("$prefix §b使い方 : /ubans info <プレイヤーネーム>");
            return true;
        }

        //醜い(見にくい)のはお許しください(´・ω・｀)
        $name       = $args[1];
        $data       = $this->getPlayerData($name);
        $ban        = $this->isUBanned_B($name);
        $warn       = $this->isWarned($name);
        if (!$this->isRegistered($name)){
            $sender->sendMessage("$prefix §a{$name}§fはサーバーに来ていません");
            return true;
        }
        if ($ban == 0 and $warn == 0){
            $sender->sendMessage("$prefix §b{$name}さんはUBan又はWarnされていません");
            return true;
        }
        $bantime    = $data["ban_time"];
        $warntime   = $data["warn_time"];
        $banreason  = $data["ban_reason"];
        $warnreason = $data["warn_reason"];
        $bansender  = $data["ban_sender"];
        $warnsender = $data["warn_sender"];
        $type       = "ban = {$ban}, warn = {$warn}";
        $info_1     = <<<INFO_1
§b------ §6$name §b------
§e[UBan]      §atrue
§e-[Reason]   §6$banreason
§e-[Date]     §6$bantime
§e-[Enforcer] §6$bansender
§e[Warn]      §atrue
§e-[Reason]   §6$warnreason
§e-[Date]     §6$warntime
§e-[Enforcer] §6$warnsender
§b------ §6$name §b------
INFO_1;
                       
        $info_2 = <<<INFO_2
§b------ §6$name §b------
§e[UBan]      §atrue
§e-[Reason]   §6$banreason
§e-[Date]     §6$bantime
§e-[Enforcer] §6$bansender 
§e[Warn]      §cfalse
§b------ §6$name §b------
INFO_2;
                        
        $info_3 = <<<INFO_3
§b------ §6$name §b------
§e[UBan]      §cfalse
§e[Warn]      §atrue
§e-[Reason]   §6$warnreason
§e-[Date]     §6$warntime
§e-[Enforcer] §6$warnsender
§b------ §6$name §b------
INFO_3;
        $array = ["ban = 1, warn = 1" => "info_1",
                  "ban = 1, warn = 0" => "info_2",
                  "ban = 0, warn = 1" => "info_3"];
        $info  = $array[$type];


        $sender->sendMessage(${$info});
        break;

        default:
        $sender->sendMessage("$prefix §b/ubans {$args[0]}というコマンドは存在しません");
        break;

        }
    }
    if ($command->getName() == "uban_txt"){
        if (!$sender instanceof Player){
                $sender_name = "管理者";
            }
        if (count($args) < 7){
            $sender->sendMessage("$prefix §b使い方 : /uban_txt <名前> <IPアドレス> <ホスト> <クライアントID> <ユニークID>  <ローID> <理由>\n".
                                 "$prefix §bUBanしたくない情報は「null」と入力してください");
            return true;
            }
        if ($args[0] == "null" or $this->BP->exists($args[0])){
            $sender->sendMessage("$prefix §bその名前は使用できません\n".
                                 "$prefix 別の名前に変更してください");
            return true;
            }

        $this->UBanByText($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $sender_name);
        $ubanbytext = <<<UBANBYTEXT
$prefix §c{$args[0]}§aをテキストでUBanしました
§6IPAddress §f: §b$args[1]
§6Host      §f: §b $args[2]
§6ClientID  §f: §b $args[3]
§6UniqueID  §f: §b$args[4]
§6RawID     §f: §b$args[5]
§6Reason    §f: §b $args[6]
UBANBYTEXT;
        $sender->sendMessage($ubanbytext);
    }
return true;
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $name   = $player->getName();
        if ($this->getPlayerData($name)["warn"] === 1 && $this->getSetting("Warnされているプレイヤーのブロックの操作を制限する") == "true"){
            $player->sendTip("§c⚠あなたはWarnされています⚠");
            $event->setCancelled();
        }
    }
    public function onBlockPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        $name   = $player->getName();
        if ($this->getPlayerData($name)["warn"] === 1 && $this->getSetting("Warnされているプレイヤーのブロックの操作を制限する") == "true"){
            $player->sendTip("§c⚠あなたはWarnされています⚠");
            $event->setCancelled();
        }
    }

    
    public function DB($sql, $return = false){
                if($return){
                    return $this->db->query($sql)->fetchArray();
                }else{
                    $this->db->query($sql);
                    return true;
                }
            }

    
    public function getSetting($setting){
        if (!$this->Setting->exists($setting))
            return false;
        
        return $this->Setting->get($setting);
    }

    public function getRawUniqueId(Player $player){
        $rawid = bin2hex($player->getRawUniqueId());

        return $rawid;
    }

    public function isRegistered($name){
        $data = $this->DB("SELECT * FROM player WHERE name = \"$name\"", true);
        if (empty($data))
            return false;

        return true;

    }

    public function isUBanned_A($name, $ip, $host, $cid, $uuid, $rawid){

        $result = $this->DB("SELECT * FROM player WHERE
            ban = \"1\" AND name = \"$name\" OR
            ban = \"1\" AND ip = \"$ip\" OR
            ban = \"1\" AND host = \"$host\" OR
            ban = \"1\" AND cid = \"$cid\" OR
            ban = \"1\" AND uuid = \"$uuid\" OR
            ban = \"1\" AND rawid = \"$rawid\"
            ", true);

        if(!empty($result))
            return true;
      
            return false;
        }

    public function isUBanned_B($name){

        $data = $this->DB("SELECT * FROM player WHERE name = \"$name\"", true);
        $ban = $data["ban"];
        return $ban;
    }

    public function UBan($name, $reason, $sender_name){
        $data = $this->getPlayerData($name);

        if (empty($data))
            return false;
        $time = $this->getTime();
        $this->DB("UPDATE player SET ban = \"1\", ban_sender = \"$sender_name\", ban_reason = \"$reason\", ban_time = \"$time\" WHERE name = \"$name\"");

        $this->BL->set($name);
        $this->BL->save();
        
        $this->BP->set($name, $reason);
        $this->BP->save();
        $this->BP->reload();

        return true;

    }

    public function UBanByText($name, $ip, $host, $cid, $uuid, $rawid, $reason, $sender_name){

        $time = $this->getTime();
        //$this->DB("UPDATE player SET ban = \"1\", ban_reason = $reason, ban_time = $time, ban_sender = $sender_name WHERE name = \"$name\"");
        $this->DB("INSERT OR REPLACE INTO player VALUES(\"$name\", \"$ip\",  \"$host\",  \"$cid\", \"$uuid\", \"$uuid\", \"$reason\", \"$reason\", \"1\", \"1\", \"$sender_name\", \"$sender_name\", \"$time\", \"$time\")");
        $this->BP->set($name, $reason);
        $this->BP->save();
        $this->BP->reload();
        $this->WP->set($name, $reason);
        $this->WP->save();
        $this->WP->reload();
        $this->BL->set($name);
        $this->BL->save();
        $this->WL->set($name);
        $this->WL->save();

    }

    public function unUBan($name){
        $data = $this->getPlayerData($name);

        if (empty($data))
            return false;
        $warn = $data["warn"];
        $this->DB("UPDATE player SET ban = \"0\", warn = \"$warn\", ban_sender = \"\", ban_reason = \"\", ban_time = \"\" WHERE name = \"$name\"");

        $this->BL->remove($name);
        $this->BL->save();
        
        $this->BP->remove($name);
        $this->BP->save();
        $this->BP->reload();

        return true;

    }

    public function Warn($name, $reason, $sender_name){
        $data = $this->getPlayerData($name);

        if (empty($data)) 
            return false;

        $time = $this->getTime();
        $this->DB("UPDATE player SET warn = \"1\", warn_sender = \"$sender_name\", warn_reason = \"$reason\", warn_time = \"$time\" WHERE name = \"$name\"");
        $this->WP->set($name, $reason);
        $this->WP->save();
        $this->WP->reload();
        
        
        $this->WL->set($name);
        $this->WL->save();

        return true;
    }

    public function Delete($name){
        $data = $this->getPlayerData($name);

        if(empty($data)) 
        return false;

        $this->DB("DELETE FROM player WHERE name = \"$name\"", true);
        return true;
    }

    /*
        サブアカウントの誤Banの解決のために使うはずだった関数。もっと根本的な部分に問題があったため今は保留。
        public function makeRandomName(){
  
        return uniqid("UBans_" . mt_rand(0, 9999999), true);
    }*/

     public function isWarned($name){

        $data = $this->DB("SELECT * FROM player WHERE name = \"$name\"", true);
        $warn = $data["warn"];
        
        return $warn;
    }

    public function unWarn($name){
        $data = $this->getPlayerData($name);
        if (empty($data))
            return false;

        $this->DB("UPDATE player SET warn = \"0\", warn_sender = \"\", warn_reason = \"\", warn_time = \"\" WHERE name = \"$name\"");

        $this->WP->remove($name);
        $this->WP->save();
        $this->WP->reload();
        
        $this->WL->remove($name);
        $this->WL->save();

        return true;
    }

    public function setDanger(Player $player){
        $nt = $player->getNameTag();
        $dn = $player->getDisplayName();

        $player->setNameTag("§c⚠§f $nt");
        $player->setDisplayName("§c⚠§f $dn");

        return true;
    }

    public function setDefaultStatus(Player $player){
        $name = $player->getName();
        if ($this->isWarned($name) == 0)
        return;

        $nt = str_replace("§c⚠§f", "", $player->getNameTag());
        $dn = str_replace("§c⚠§f", "", $player->getDisplayName());
        $player->setNameTag($nt);
        $player->setDisplayName($dn);
    }

    public function getPlayerData($name){
        return $this->DB("SELECT * FROM player WHERE name = \"$name\"", true);
    }

    public function getOnlinePlayerData(Player $player){
    	$data["name"]  = $player->getName();
        $data["ip"]    = $player->getAddress();
        $data["host"]  =  gethostbyaddr($data["ip"]);
        $data["cid"]   = $player->getClientId();
        $data["uuid"]  = $player->getUniqueId()->ToString();
        $data["rawid"] = $this->getRawUniqueId($player);
        return $data;
    }
    public function Register($name, $ip, $host, $cid, $uuid, $rawid){

    $this->DB("INSERT OR REPLACE INTO player VALUES(\"$name\", \"$ip\",  \"$host\",  \"$cid\", \"$uuid\", \"$rawid\", \"\", \"\", \"0\", \"0\", \"\", \"\", \"\", \"\")");
    return true;
    }
    public function Update($name, $ip, $host, $cid, $uuid, $rawid){

    $this->DB("UPDATE player SET name = \"$name\", ip = \"$ip\", host = \"$host\", cid = \"$cid\", uuid = \"$uuid\", rawid = \"$rawid\" WHERE name = \"$name\"");
    return true;
    }

    public function GetAllBannedPlayerName(){
    $names = $this->BL->getAll();
    return $names;
    }
    public function GetAllWarnPlayerName(){
    $names = $this->WL->getAll();
    return $names;
    }
    function DevFunction($name, $ip, $host, $cid, $uuid, $rawid){
        $ban  = 1;
        $warn = 0;
       $this->DB("INSERT OR REPLACE INTO player VALUES(\"$name\", \"$ip\",  \"$host\",  \"$cid\", \"$uuid\", \"$rawid\", \"\", \"\", \"$ban\", \"$warn\", \"\", \"\", \"\", \"\")");
    }
    function getTime(){
        $time = date("Y年m月d日H時i分s秒");
        return $time;
    }
    function Broadcast($message, $type = "M"){
        switch ($type){
            default:
            case "M":
            $type = "BroadcastMessage";
            break;
            case "T":
            $type = "BroadcastTip";
            break;
            case "P":
            $type = "BroadcastPopup";
            break;

        }
        $this->s->$type($message);

    }
}
