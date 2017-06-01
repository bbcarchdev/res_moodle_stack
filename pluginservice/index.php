<?php
require_once(__DIR__ . '/vendor/autoload.php');

use \Slim\App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use res\liblod\LOD;

$app = new \Slim\App();

$acropolisUrl = getenv('ACROPOLIS_URL');

/* Flatten an array with non-numeric keys and array values; the key is
   added as a property $keyAttribute to the value array */
function flattenArray($arr, $keyAttribute)
{
    $arrOut = array();

    foreach($arr as $key => $arrValue)
    {
        $arrValue[$keyAttribute] = $key;
        $arrOut[] = $arrValue;
    }

    return $arrOut;
}

/* Merge 2 arrays with non-numeric keys; where the same key occurs in both arrays,
   the data for the value of that key in the resulting array is itself an
   array, containing as many non-empty values from the combined arrays as
   possible */
function mergeArrays($arr1, $arr2)
{
    $arrOut = array();

    foreach($arr1 as $key => $data)
    {
        if(array_key_exists($key, $arr2))
        {
            if(is_array($arr1[$key]) && is_array($arr2[$key]))
            {
                $arrOut[$key] = mergeArrays($arr1[$key], $arr2[$key]);
            }
            else if(empty($arr2[$key]))
            {
                $arrOut[$key] = $arr1[$key];
            }
            else
            {
                $arrOut[$key] = $arr2[$key];
            }
        }
        else
        {
            $arrOut[$key] = $data;
        }
    }

    foreach($arr2 as $key => $data)
    {
        if(!array_key_exists($key, $arr1))
        {
            $arrOut[$key] = $data;
        }
    }

    return $arrOut;
}

/* Extract media statements from a resource;
   returns
   array('players' => ..., 'contents' => ..., 'pages' => ...)
 */
function extractMedia($lodInstance)
{
    $result = array(
        'players' => array(),
        'contents' => array(),
        'pages' => array()
    );

    $label = "{$lodInstance['dcterms:title,rdfs:label']}";
    $description = "{$lodInstance['dcterms:description,rdfs:comment']}";

    foreach($lodInstance['mrss:player'] as $player)
    {
        $result['players'] = mergeArrays($result['players'],
            array(
                $player->value => array(
                    'source_uri' => $lodInstance->uri,
                    'label' => $label,
                    'description' => $description,
                    'thumbnail' => "{$lodInstance['schema:thumbnailUrl']}",
                    'height_px' => "{$lodInstance['exif:height']}",
                    'width_px' => "{$lodInstance['exif:width']}",
                    'date' => "{$lodInstance['dcterms:date']}",
                    'location' => "{$lodInstance['lio:location']}"
                )
            )
        );
    }

    foreach($lodInstance['mrss:content'] as $content)
    {
        $result['contents'] = mergeArrays($result['contents'],
            array(
                $content->value => array(
                    'source_uri' => $lodInstance->uri,
                    'label' => $label
                )
            )
        );
    }

    foreach($lodInstance['foaf:page'] as $page)
    {
        $result['pages'] = mergeArrays($result['pages'],
            array(
                $page->value => array(
                    'source_uri' => $lodInstance->uri,
                    'label' => $label
                )
            )
        );
    }

    return $result;
}

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
    $limit = intval($request->getQueryParam('limit', $default=10));
    $offset = intval($request->getQueryParam('offset', $default=0));

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

            // if we can't resolve the slot resource, don't do anything
            if(!$slotResource)
            {
                continue;
            }

            // make proxy links to /api/topic/ for each resource
            foreach($slotResource['olo:item'] as $slotItem)
            {
                if($slotItem->isResource())
                {
                    $topic = $lod[$slotItem->value];

                    $label = "{$topic['dcterms:title,rdfs:label']}";

                    $isInfo = preg_match('|^Information about |', $label);

                    // reject any foaf:Document resources whose label starts
                    // with "Information about" - these are useless
                    if($topic->hasType('foaf:Document') && $isInfo)
                    {
                        continue;
                    }

                    $topicApiUri = $request->getUri()->withPath('/api/topic');
                    $topicApiUri = $topicApiUri->withQuery('uri=' . $topic->uri);

                    $result['items'][] = array(
                        'api_uri' => "$topicApiUri",
                        'label' => $label,
                        'description' => "{$topic['dcterms:description,rdfs:comment']}"
                    );
                }
            }
        }
    }

    // do we have more results? (yes if xhtml:next statement present)
    $result['hasNext'] = !empty("{$searchResultResource['xhtml:next']}");

    return $response->withJson($result);
});

// proxy for topic requests to Acropolis
// call with /api/topic?uri=<acropolis URI>;
// as we fetch RDF about the media, we merge it with anything we already
// know about that piece of media
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
        'description' => "{$topic['dcterms:description,rdfs:comment']}",
        'players' => NULL,
        'contents' => NULL,
        'pages' => NULL
    );

    $players = array();
    $contents = array();
    $pages = array();

    // find resources which are owl:sameAs the topic and extract media from
    // them
    $usefulUris = array($topic->uri) + $lod->getSameAs($topic->uri);

    foreach($usefulUris as $usefulUri)
    {
        $usefulResource = $lod[$usefulUri];
        $media = extractMedia($usefulResource);
        $players = mergeArrays($players, $media['players']);
        $contents = mergeArrays($contents, $media['contents']);
        $pages = mergeArrays($pages, $media['pages']);
    }

    // if we have olo:slots on the topic, fetch those and extract their media
    // too
    foreach($topic['olo:slot'] as $oloSlot)
    {
        $slotResource = $lod[$oloSlot->value];
        foreach($slotResource['olo:item'] as $slotItem)
        {
            $slotItemResource = $lod->fetch($slotItem->value);
            $media = extractMedia($slotItemResource);
            $players = mergeArrays($players, $media['players']);
            $contents = mergeArrays($contents, $media['contents']);
            $pages = mergeArrays($pages, $media['pages']);

            // also get any resources which are sameAs the slot items, then
            // find their foaf:primaryTopics and get media for them
            $sameasUris = $lod->getSameAs($slotItem->value);
            foreach($sameasUris as $sameasUri)
            {
                $sameasResource = $lod[$sameasUri];

                foreach($sameasResource['foaf:primaryTopic'] as $primaryTopic)
                {
                    $primaryTopicResource = $lod->fetch($primaryTopic->value);
                    $media = extractMedia($primaryTopicResource);
                    $players = mergeArrays($players, $media['players']);
                    $contents = mergeArrays($contents, $media['contents']);
                    $pages = mergeArrays($pages, $media['pages']);
                }
            }
        }
    }

    // flatten out the arrays
    $result['players'] = flattenArray($players, 'uri');
    $result['contents'] = flattenArray($contents, 'uri');
    $result['pages'] = flattenArray($pages, 'uri');

    return $response->withJson($result);
});

$app->run();
