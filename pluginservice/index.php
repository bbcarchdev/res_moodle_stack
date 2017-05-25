<?php
require_once(__DIR__ . '/vendor/autoload.php');

use \Slim\App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

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
$app->get('/api/search', function(Request $request, Response $response) use($acropolisUrl) {
    $query = $request->getQueryParam('q', $default=null);

    if(!$query)
    {
        return 'please supply a query using the ?q= querystring parameter';
    }
    else
    {
        return 'will search for ' . $query . ' on ' . $acropolisUrl;
    }
});

// proxy for topic requests to Acropolis
$app->get('/api/topic/{topicIdentifier}', function(Request $request, Response $response, $args) use($acropolisUrl) {
    $topicIdentifier = $args['topicIdentifier'];
    return 'here would be results for topic ' . $topicIdentifier . ' from ' . $acropolisUrl;
});

$app->run();
