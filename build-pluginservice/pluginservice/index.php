<?php
require_once(__DIR__ . '/vendor/autoload.php');

use \Slim\App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use res\libres\RESClient;

$app = new \Slim\App();

$acropolisUrl = getenv('ACROPOLIS_URL');

/*
 * paths:
 *
 * - /?callback=<callback URL> -> show URI for searching RES and selecting media
 *   resources; when a resource is selected, the UI is redirected to
 *   <callback URL>?media=<JSON-encoded representation of the selected resource>
 * - /api/search?q=<search> -> perform a search and return list of matching topics
 * - /api/topic/<topic ID> -> return topic data, including list of related media;
 *   when a piece of media is clicked, invoke a callback URL (if provided)
 *   with details of that piece of media (to populate Moodle file picker)
 */

// single HTML page: UI for searching Acropolis, showing search results, and
// displaying a topic with its media
$app->get('/', function (Request $request, Response $response)
{
    $html = file_get_contents(__DIR__ . '/ui.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html')
                    ->withHeader('Content-Location', '/ui.html');
});

// proxy for searches on Acropolis
// call with /api/search?q=<search term>
$app->get('/api/search', function(Request $request, Response $response) use($acropolisUrl)
{
    $query = $request->getQueryParam('q', $default=NULL);
    $limit = intval($request->getQueryParam('limit', $default=10));
    $offset = intval($request->getQueryParam('offset', $default=0));

    $client = new RESClient($acropolisUrl);

    $result = $client->search($query, $limit, $offset);

    // for each item in the results, construct a URI pointing at the plugin
    // service API, in the form
    // http://<plugin service domain and port>/api/topic?uri=<topic URI>
    $baseApiUri = $request->getUri()->withPath('/api/topic');

    foreach($result['items'] as $index => $item)
    {
        $item['api_uri'] = "{$baseApiUri->withQuery('uri=' . $item['topic_uri'])}";
        $result['items'][$index] = $item;
    }

    return $response->withJson($result);
});

// proxy for topic requests to Acropolis
$app->get('/api/topic', function(Request $request, Response $response) use($acropolisUrl)
{
    $topicUri = $request->getQueryParam('uri', $default=NULL);

    $client = new RESClient($acropolisUrl);

    $result = $client->topic($topicUri);

    return $response->withJson($result);
});

$app->run();
