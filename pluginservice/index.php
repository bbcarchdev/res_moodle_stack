<?php
require_once(__DIR__ . '/vendor/autoload.php');

use \Slim\App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use res\liblod\LOD;

$app = new \Slim\App();

$acropolisUrl = getenv('ACROPOLIS_URL');

/*
 * paths:
 *
 * - /?q=<search> -> perform a search and return list of matching topics
 * - /topic/<topic ID> -> return topic data, including list of related media;
 *   when a piece of media is clicked, invoke a callback URL (if provided)
 *   with details of that piece of media (to populate Moodle file picker)
 */

// single HTML page: UI for searching Acropolis, showing search results, and
// displaying a topic with its media
$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/ui.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html')
                    ->withHeader('Content-Location', '/ui.html');
});

// proxy for searches on Acropolis
// call with /api/search?q=<search term>
$app->get('/api/search', function(Request $request, Response $response) use($acropolisUrl) {
    $query = $request->getQueryParam('q', $default=NULL);
    $limit = $request->getQueryParam('limit', $default=10);
    $offset = $request->getQueryParam('', $default=0);

    $result = array(
        'acropolis_uri' => NULL,
        'query' => $query,
        'limit' => $limit,
        'offset' => $offset,
        'hasNext' => FALSE,
        'items' => array()
    );

    if($query)
    {
        $uri = $acropolisUrl .
               '?q=' . urlencode($query) .
               '&limit=' . urlencode($limit) .
               '&offset=' . urlencode($offset) .
               '&media=any';

        $result['acropolis_uri'] = $uri;

        $lod = new LOD();
        $searchResultResource = $lod[$uri];

        foreach($searchResultResource['olo:slot'] as $slot)
        {
            $slotUri = $slot->value;
            $slotResource = $lod[$slotUri];

            if(!$slotResource)
            {
                continue;
            }

            foreach($slotResource['olo:item'] as $slotItem)
            {
                if($slotItem->isResource())
                {
                    $topic = $lod[$slotItem->value];

                    $topicApiUri = $request->getUri()->withPath('/api/topic');
                    $topicApiUri = $topicApiUri->withQuery('uri=' . $topic->uri);

                    $result['items'][] = array(
                        'api_uri' => "$topicApiUri",
                        'label' => "{$topic['dcterms:title,rdfs:label']}",
                        'description' => "{$topic['dcterms:description,rdfs:comment']}"
                    );
                }
            }
        }
    }

    return $response->withJson($result);
});

// proxy for topic requests to Acropolis
// call with /api/topic?uri=<acropolis URI>
$app->get('/api/topic', function(Request $request, Response $response) use($acropolisUrl) {
    $topicUri = $request->getQueryParam('uri', $default=NULL);

    $lod = new LOD();
    $topic = $lod[$topicUri];

    if(!$topic)
    {
        return $response->withJson(NULL);
    }

    $result = array(
        'uri' => "{$topic->uri}",
        'label' => "{$topic['rdfs:label,dcterms:title']}",
        'description' => "{$topic['dcterms:description,rdfs:comment']}"
    );

    // get olo:slots
    $slots = $topic['olo:slot'];

    // get olo:items for those olo:slots
    $itemUris = array();
    foreach($slots as $slot)
    {
        $slotResource = $lod["$slot"];
        $itemUris[] = '' . $slotResource['olo:item'];
    }

    // fetch each item (NB these items are RES proxy resources without much
    // detail in them)
    foreach($itemUris as $itemUri)
    {
        $lod->fetch($itemUri);

        // look for resources which are owl:sameAs the item URIs; this is
        // because the original holds useful information which the RES proxy
        // doesn't have, so we'd prefer to use the original if we can


        // resolve those resources - we just use whatever RES has cached, so
        // we don't need yet another fetch; if they can't be resolved, just use
        // the proxy resource instead, which should have at least an mrss:player,
        // mrss:content or foaf:page
    }

    return $response->withJson($result);
});

$app->run();
