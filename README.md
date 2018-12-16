# UBans
### API
```php
※ “ $this->UBans = Server::getInstance()->getPluginManager()->getPlugin(“UBans”); “


————————————————————————————————————————————————————————————

//BAN THE PLAYER
UBan($name, $reason, $sender_name)

//Returns false if the player's name is not registered in the DB and true if it succeeds in the UBan

$name = PlayerName
$reason = Reason
$sender_name = SenderName

ex : $this->UBans->addUBan(“Ramen_is_Goooooooood!!”, “I don't know”, “TukemenHa”);

————————————————————————————————————————————————————————————

//UNBAN THE PLAYER

unUBan($name)

//Returns false if the player's name is not registered in the DB and true if it succeeds in the UBan.

$name = PlayerName

ex) : $this->UBans->unUBan(“Ramen_is_Goooooooood!!”);

————————————————————————————————————————————————————————————

//A function that refers to all information in the database and checks whether the player is banned.


isUBanned_A($name, $ip, $host, $cid, $uuid, $rawid)

//Returns false if the player's name is not registered in the DB and true if it succeeds in the UBan.

$name = PlayerName
$ip = IPAddress
$host = HOST
$cid = ClientID
$uuid = UniqueID
$rawid = RawID

ex) : $this->UBans->isUBanned_A($name, $ip, $host, $cid, $uuid, $rawid);

————————————————————————————————————————————————————————————
//Function to check if it is being banned by that name
isUBanned_B($name)

※Returns 1 if it is Banned, 0 if not.

$name = PlayerName

ex) : $this->UBans->isUBanned_B($name);

————————————————————————————————————————————————————————————



//Function to warn player

Warn($name, $reason, $sender_name)

※Returns false if the player's name is not registered in the DB and true if it succeeds in the warn.

$name = PlayName
$reason = reason
$sender_name = SenderName

ex) : $this->UBans->addWarn(“Ramen_is_Goooooooood!!”, “I don't know”, “TukemenHa”);

————————————————————————————————————————————————————————————

//UNWARN THE PLAYER
unWarn($name)

$name = PlayerName

ex) : $this->UBans->unWarn(“Ramen_is_Goooooooood!!”);

————————————————————————————————————————————————————————————


isWarned($name)


$name = PlayerName

ex) : $this->UBans->isWarned($name);

————————————————————————————————————————————————————————————

setDanger(Player $player)

$player = PlayerObject

ex) : $this->UBans->setDanger(Server::getInstance()->getPlayer(“Ramen_is_Goooooooood!!”));

————————————————————————————————————————————————————————————

setDefaultStatus(Player $player)

$player = PlayerObject

ex) : $this->UBans->setDefaultStatus(Server::getInstance()->getPlayer(“Ramen_is_Goooooooood!!”));

———————————————————————————————————————————————————————————

UBanByText($name, $ip, $host, $cid, $uuid, $rawid, $reason, $sender_name)

$name = PlayerName
$ip = IPAddress
$cid = ClientID
$uuid = UniqueID
$reason = Reason
$rawid = RawID
$sender_name =　SenderName
例 : $this->UBans->UBanByText(“Ramen_is_Goooooooood!!”, “Null”, “Null”, “Null”, “Null”, “Null”, “つけめん”, “TukemenHa”);
————————————————————————————————————————————————————————————
```
