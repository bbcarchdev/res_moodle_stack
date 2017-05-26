<?php
require_once(__DIR__ . '/vendor/autoload.php');

use res\liblod\LOD;

$lod = new LOD();

$uri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4#id';

// get the resource at the URI
$resource = $lod[$uri];

// get olo:slots
$slots = $resource['olo:slot'];

// get olo:items for those olo:slots
$itemUris = array();
foreach($slots as $slot)
{
    $slotResource = $lod["$slot"];
    $itemUris[] = '' . $slotResource['olo:item'];
}

// fetch the olo:items
$playerUris = array();
foreach($itemUris as $itemUri)
{
    $item = $lod->fetch($itemUri);

    // get the players for those items
    foreach($item['mrss:player'] as $player)
    {
        $playerUris[] = '' . $player;
    }
}

var_dump($playerUris);
