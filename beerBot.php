<?php
// This is the Beer Bot, which reads data from the MySQL DB and sends it to
// Telegram users.
// 01.02.2016 / Hannes Badertscher

include("TelegramBotPHP/Telegram.php");

$base = dirname(dirname(__FILE__)); 
include($base . '/settings/db_settings.php');
include($base . '/settings/bierbot_id.php');

// Shops
$shops = array( 'denner',
                'coop',
                'coop-megastore',
                'volg',
                'aldi',
                'lidl',
                'spar');

// Setup chat
$telegram = new Telegram($bot_id);
$chat_id = $telegram->ChatID();

// Parse command and arguments
$rawstr = $telegram->Text();
preg_match("/\/[^\s\z$]*/", $rawstr, $cmd);
$cmd = array_values($cmd)[0];
$args = preg_split("/\/[^\s\z$]*/", $rawstr);
$args = strtolower(trim(implode(" ", $args)));

// Get sender info
$vorname= $telegram->FirstName();
$nachname=$telegram->LastName();
$username=$telegram->UserName();

switch($cmd)
{
    case "/start":
    case "/start@BierAktionBot":
    case "/help":
    case "/help@BierAktionBot":
        $content = array('chat_id' => $chat_id, 'text' => 
            "Hello. I am BierAktionBot. I try to help you find good beers at cheap prices in Switzerland." .
            "Type /getBeers to get a list of all beers which are discounted at the moment."
        );
        $telegram->sendMessage($content);
        break;

    case "/getbeers":
    case "/getbeers@BierAktionBot":
    case "/getBeers":
    case "/getBeers@BierAktionBot":

        // Connect to DB
        $pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName, $dbUser, $dbPW);
        $dbGetStore = $pdo->prepare("SELECT * FROM beers WHERE place = ?");
        
        foreach ($shops as $thisstore) {
            $dbGetStore->execute(array($thisstore));
            if ($dbGetStore->rowCount() > 0) {
                $msg = ucfirst($thisstore) . ":\n";
                while ($row = $dbGetStore->fetch()) {
                    $msg = $msg . $row['beer'] . ' für ' . $row['pricenew'] . ' statt ' . $row['priceold'] . ".\n";
                }
                $content = array('chat_id' => $chat_id, 'text' => $msg);
                $telegram->sendMessage($content);
            }
        }
        break;

    case "/getstore":
    case "/getstore@BierAktionBot":
    case "/getStore":
    case "/getStore@BierAktionBot":
    
        // Connect to DB
        $pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName, $dbUser, $dbPW, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
        $dbGetStore = $pdo->prepare("SELECT * FROM beers WHERE place = ?");
        $dbGetEasteregg = $pdo->prepare("SELECT * FROM beers WHERE place = ? AND easteregg=1");
        
        // First check easter eggs.
        $dbGetEasteregg->execute(array($args));    
        while ($row = $dbGetEasteregg->fetch()) {
            $content = array('chat_id' => $chat_id, 'text' => $row['beer']);
            $telegram->sendMessage($content);
            return 0;
        }
    
        // Fuzzy matching to get most relevant store
        $closest = -1;
        foreach ($shops as $thisstore) {
            $lev = levenshtein($args, $thisstore);
            if ($lev == 0) {
                $thestore = $thisstore;
                $closest = 0;
                break;
            }
            if ($lev <= $closest || $closest < 0) {
                $closest = $lev;
                $thestore = $thisstore;
            }
        }
        
        if ($closest > 3) {
            $msg = "I don't know that store.";
        }
        
        else {
            $msg = ucfirst($thestore) . ":\n";
            $dbGetStore->execute(array($thestore));
            while ($row = $dbGetStore->fetch()) {
                $msg = $msg . $row['beer'] . ' für ' . $row['pricenew'] . ' statt ' . $row['priceold'] . ".\n";
            }
        }
        $content = array('chat_id' => $chat_id, 'text' => $msg);
        $telegram->sendMessage($content);
        break;
        
    default:
        $content = array('chat_id' => $chat_id, 'text' => $vorname . ", what are you trying to say?");
        $telegram->sendMessage($content);

}

?>