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
    $query = $request->getQueryParam('q', $default=null);
    $limit = $request->getQueryParam('limit', $default=10);
    $offset = $request->getQueryParam('', $default=0);

    $results = array();

    if($query)
    {
        $url = $acropolisUrl .
               '?q=' . urlencode($query) .
               '&limit=' . urlencode($limit) .
               '&offset=' . urlencode($offset);

        $lod = new LOD();
        $lod->fetch($url);

        $resource = $lod[$url];

        foreach($resource['olo:slot'] as $slot)
        {
            $uri = $slot->value;

            $slotResource = $lod[$uri];

            if (!$slotResource)
            {
                continue;
            }

            foreach($slotResource['olo:item'] as $slotItem)
            {
                if($slotItem->isResource())
                {
                    $topic = $lod[$slotItem->value];

                    $topicUri = $request->getUri()->withPath('/api/topic');
                    $topicUri = $topicUri->withQuery('uri=' . $topic->uri);

                    $results[] = array(
                        'uri' => "$topicUri",
                        'label' => "{$topic['dcterms:title,rdfs:label']}",
                        'description' => "{$topic['dcterms:description,rdfs:comment']}"
                    );
                }
            }
        }
    }

    return $response->withJson($results);
});

// proxy for topic requests to Acropolis
// call with /api/topic?uri=<acropolis URI>
$app->get('/api/topic', function(Request $request, Response $response) use($acropolisUrl) {
    $topicUri = $request->getQueryParam('uri', $default=null);;
    return 'here would be results for topic ' . $topicUri . ' from ' . $acropolisUrl;
});

$app->run();
