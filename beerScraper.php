<?php
// This is a Web-Scrapper, which gets the current discounts on beer from
// www.aktionis.ch
// 01.02.2016 / Hannes Badertscher

include("Curl.php");

$base = dirname(dirname(__FILE__)); 
include($base . '/settings/db_settings.php');

// Connect to DB
$pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName, $dbUser, $dbPW);
$shopFind = $pdo->prepare("SELECT name FROM stores WHERE 1");
$shopDate = $pdo->prepare("UPDATE stores SET date=:thedate WHERE name=:theplace");
$dbInsert = $pdo->prepare("INSERT INTO beers (place, beer, priceold, pricenew, easteregg) VALUES(:theplace, :thebeer, :theoldprice, :thenewprice, 0)");
$dbDelete = $pdo->prepare("DELETE FROM beers where place = ?");

// Find all shops
$shopFind->execute();

// Go through all shops
while ($thisShop = $shopFind->fetch()) {
    $thisShop = $thisShop[0];

    // Delete existing actions
    $dbDelete->execute(array($thisShop));

    // Get raw HTML
    $curl = new Curl();
    $html = $curl->get("http://www.aktionis.ch/vendors/" . $thisShop . "?c=8-26");

    // Remove newlines
    $html = trim(preg_replace('/\s+/', ' ', $html));
    $splittedHtml = preg_split("/<div class=\"card-inner\">/", $html);

    foreach ($splittedHtml as $thisHtml) {

        // Find beer name
        preg_match("/<h3 class=\"card-title text-truncate\">([^<]*)<\/h3>/", $thisHtml, $regexpName);
        $parsedBeer = $regexpName[1];

        if ($parsedBeer)
        {
            $parsedBeer = trim($parsedBeer);

            // Get Beer Prices
            preg_match("/<span class=\"price-new\">([^<]*)<\/span>/", $thisHtml, $regexpPriceNew);
            preg_match("/<span class=\"price-old\">([^<]*)<\/span>/", $thisHtml, $regexpPriceOld);
            preg_match("/<span class=\"card-date\">([^<]*)<\/span>/", $thisHtml, $regexpDate);
            $parsedPriceNew = $regexpPriceNew[1];
            $parsedPriceOld = $regexpPriceOld[1];
            $parsedDate = $regexpDate[1];
            
            if (!$parsedPriceNew) {
                // Try some ugly hacks to recover the data
                preg_match("/<span class=\"price-text\">([^<]*)<\/span>([^<]*)<\/span>/", $thisHtml, $regexpPriceNew);
                $parsedPriceNew = $regexpPriceNew[2];
                $parsedPriceOld = "";
            }

            // Save Beers
            $thisBeer = array(  'theplace' => $thisShop, 
                                'thebeer' => $parsedBeer,
                                'theoldprice' => $parsedPriceOld,
                                'thenewprice' => $parsedPriceNew );
            $dbInsert->execute($thisBeer);

	    // Set date
	    $shopDate->execute(array('theplace' => $thisShop, 'thedate' => $parsedDate));
        }

    }

}

// Manually remove crap
$dbRemoveCrap = $pdo->prepare("DELETE FROM beers WHERE beer = ?");
$dbRemoveCrap->execute(array("Rivella"));

// Close DB
$pdo = null;

?>
