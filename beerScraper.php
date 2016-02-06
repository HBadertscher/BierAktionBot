<?php
// This is a Web-Scrapper, which gets the current discounts on beer from
// www.aktionis.ch
// 01.02.2016 / Hannes Badertscher

include("Curl.php");

$base = dirname(dirname(__FILE__)); 
include($base . '/settings/db_settings.php');

// Shops
$shops = array( 0 => 'denner',
                1 => 'coop',
                2 => 'coop-megastore',
                3 => 'volg',
                4 => 'aldi',
                5 => 'lidl',
                6 => 'spar');

// Connect to DB
$pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName, $dbUser, $dbPW);
$dbInsert = $pdo->prepare("INSERT INTO beers (place, beer, priceold, pricenew) VALUES(:theplace, :thebeer, :theoldprice, :thenewprice)");
$dbDelete = $pdo->prepare("DELETE FROM beers where place = ?");

// Go through all shops
foreach ($shops as $thisShop) {

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
        preg_match("/<h3 class=\"card-title\">([^<]*)<\/h3>/", $thisHtml, $regexpName);
        $parsedBeer = $regexpName[1];

        if ($parsedBeer)
        {
            $parsedBeer = trim($parsedBeer);

            // Get Beer Prices
            preg_match("/<span class=\"price-new\">([^<]*)<\/span>/", $thisHtml, $regexpPriceNew);
            preg_match("/<span class=\"price-old\">([^<]*)<\/span>/", $thisHtml, $regexpPriceOld);
            $parsedPriceNew = $regexpPriceNew[1];
            $parsedPriceOld = $regexpPriceOld[1];
            
            if (!$parsedPriceNew) {
                // Try some ugly hacks to recover the data
                preg_match("/<span class=\"price-text\">([^<]*)<\/span>([^<]*)<\/span>/", $thisHtml, $regexpPriceNew);
                $parsedPriceNew = $regexpPriceNew[2];
                $parsedPriceOld = "";
            }

            // Create array
            $thisBeer = array(  'theplace' => $thisShop, 
                                'thebeer' => $parsedBeer,
                                'theoldprice' => $parsedPriceOld,
                                'thenewprice' => $parsedPriceNew );

            // Save to DB
            $dbInsert->execute($thisBeer);
        }

    }

}

// Manually remove crap
$dbRemoveCrap = $pdo->prepare("DELETE FROM beers WHERE beer = ?");
$dbRemoveCrap->execute(array("Rivella"));

// Close DB
$pdo = null;

?>