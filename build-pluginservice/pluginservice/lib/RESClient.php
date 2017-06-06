<?php
namespace res\libres;

require_once(__DIR__ . '/../vendor/autoload.php');

use res\liblod\LOD;

/* Flatten an array with non-numeric keys and array values; the key is
   added as a property $keyAttribute to the value array; e.g.

   $arr = array(
     'foo' => array('value' => 1),
     'bar' => array('value' => 2)
   );
   flattenArray($arr, 'name');

   returns

   array(
     array('name' => 'foo', 'value' => 1),
     array('name' => 'bar', 'value' => 2)
   )

   (i.e. the keys of the original array have become 'name' properties in each
   member of the output array)
   */
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
   the data for the value of that key in the output array is itself an
   array, containing as many non-empty values from the combined arrays as
   possible, preferring the value from $arr2 if both are set */
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
   where each value of the array is itself an array
   keyed by the URI of the media resource and with an array of data about
   the media for its values, e.g.

   'http://foo.bar/1/player' => array(
     'source_uri' => '<Acropolis URI>',
     'label' => 'label',
     'height_px' => 999,
     ...
   )

   These are eventually flattened out, so that if we get data about a
   player URI from multiple places, they are merged together to form a
   comprehensive array of data about that player.
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
                    'height_px' => intval("{$lodInstance['exif:height']}"),
                    'width_px' => intval("{$lodInstance['exif:width']}"),
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
                    'label' => $label,
                    'description' => $description
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
                    'label' => $label,
                    'description' => $description
                )
            )
        );
    }

    return $result;
}

/* Client for RES */
class RESClient
{
    private $acropolisUrl;

    public function __construct($acropolisUrl)
    {
        $this->acropolisUrl = $acropolisUrl;
    }

    /*
     * Search RES for topics with related media.
     */
    public function search($query, $limit=10, $offset=0)
    {
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
            $uri = $this->acropolisUrl .
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

                        $desc = "{$topic['dcterms:description,rdfs:comment']}";

                        $result['items'][] = array(
                            'topic_uri' => $topic->uri,
                            'label' => $label,
                            'description' => $desc
                        );
                    }
                }
            }
        }

        // do we have more results? (yes if xhtml:next statement present)
        $result['hasNext'] = !empty("{$searchResultResource['xhtml:next']}");

        return $result;
    }

    /*
     * Fetch data about a single topic URI and collect any related media
     * we know about.
     *
     * As we fetch RDF about the media, merge it with anything we already
     * know about the media.
     */
    public function topic($topicUri)
    {
        $lod = new LOD();
        $topic = $lod[$topicUri];

        if(!$topic)
        {
            return NULL;
        }

        $result = array(
            'uri' => "{$topic->uri}",
            'label' => "{$topic['rdfs:label,dcterms:title']}",
            'description' => "{$topic['dcterms:description,rdfs:comment']}",
            'players' => NULL,
            'content' => NULL,
            'pages' => NULL
        );

        $players = array();
        $contents = array();
        $pages = array();

        // find resources which are owl:sameAs the topic and extract media from
        // them, as well as from the topic itself
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

            $sameasUris = array();

            foreach($slotResource['olo:item'] as $slotItem)
            {
                $slotItemResource = $lod->fetch($slotItem->value);
                $media = extractMedia($slotItemResource);
                $players = mergeArrays($players, $media['players']);
                $contents = mergeArrays($contents, $media['contents']);
                $pages = mergeArrays($pages, $media['pages']);

                // also get any resources which are sameAs the slot items
                $sameasUris = $lod->getSameAs($slotItem->value);
            }

            // find foaf:primaryTopics of any resources which are sameAs the
            // slot items and fetch RDF for them (this is useful for finding
            // bbcimages data)
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

        // flatten out the arrays
        $result['players'] = flattenArray($players, 'uri');
        $result['content'] = flattenArray($contents, 'uri');
        $result['pages'] = flattenArray($pages, 'uri');

        return $result;
    }
}
