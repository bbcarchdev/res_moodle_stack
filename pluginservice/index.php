<?php
require_once(dirname(__FILE__) . '/vendor/autoload.php');

use \Slim\App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use res\liblod\LOD;


$app = new \Slim\App();

$acropolis_url = getenv('ACROPOLIS_URL');

/*
 * paths:
 *
 * - /?q=<search> -> perform a search and return list of matching topics
 * - /topic/<topic ID> -> return topic data, including list of related media
*/
// proxy for searches on Acropolis
$app->get('/', function(Request $request, Response $response) use($acropolis_url) {
    $query = $request->getQueryParam('q', $default=null);

    if(!$query)
    {
        return 'please supply a query using the ?q= querystring parameter';
    }
    else
    {
        return 'will search for ' . $query . ' on ' . $acropolis_url;
    }
});

// proxy for topic requests to Acropolis
$app->get('/topic/{topicIdentifier}', function(Request $request, Response $response, $args) use($acropolis_url) {
    $topicIdentifier = $args['topicIdentifier'];
    return 'here would be results for topic ' . $topicIdentifier . ' from ' . $acropolis_url;
});

$app->run();
