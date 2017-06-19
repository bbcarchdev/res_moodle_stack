<?php
namespace res\libres;

require_once(__DIR__ . '/../vendor/autoload.php');

use res\liblod\LOD;
use res\liblod\Rdf;

function isVideo($lodinstance)
{
    return $lodinstance->hasType(
        'dcmitype:MovingImage',
        'schema:Movie',
        'schema:VideoObject',
        'po:TVContent'
    );
}

function isAudio($lodinstance)
{
    return $lodinstance->hasType(
        'dcmitype:Sound',
        'schema:AudioObject',
        'po:RadioContent'
    );
}

function isImage($lodinstance)
{
    return $lodinstance->hasType(
        'dcmitype:StillImage',
        'schema:Photograph',
        'schema:ImageObject',
        'foaf:Image'
    );
}

function isText($lodinstance)
{
    return $lodinstance->hasType(
        'dcmitype:Text'
    );
}

/* returns media type if the LODInstance $lodinstance has rdf:type <media type>,
   where <media type> is a recognisable media RDF type; NULL otherwise;
   media type is one of
   'audio', 'video', 'image', 'text' */
function getMediaType($lodinstance)
{
    // early return if $lodinstance is not set
    if(empty($lodinstance))
    {
        return NULL;
    }

    if(isVideo($lodinstance))
    {
        return 'video';
    }
    else if(isAudio($lodinstance))
    {
        return 'audio';
    }
    else if(isImage($lodinstance))
    {
        return 'image';
    }
    else if(isText($lodinstance))
    {
        return 'text';
    }

    return NULL;
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
     * $media is one of 'audio', 'image', 'text' or 'video'
     */
    public function search($query, $media, $limit=10, $offset=0)
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
                   '&media=' . urlencode($media) .
                   '&limit=' . urlencode($limit) .
                   '&offset=' . urlencode($offset);

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

                        // reject any foaf:Document resources whose label starts
                        // with "Information about" - these are useless
                        $isInfo = preg_match('|^Information about |', $label);
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
     * Fetch data about a single proxy URI and its olo:slot resources.
     * Convert into an associative array about the proxy and its media.
     * $media: one of 'image', 'video', 'text' or 'audio'
     * $format: 'json' (return convenient JSON representation) or 'rdf'
     * (get raw RDF for all relevant resources)
     *
     * NB this has to follow an inference chain and do lots of fetches to
     * get enough data to populate the Moodle UI properly.
     */
    public function proxy($proxyUri, $media, $format='json')
    {
        $lod = new LOD();
        $proxy = $lod->fetch($proxyUri);

        if(!$proxy)
        {
            return ($format === 'json' ? NULL : '');
        }

        $proxyLabel = "{$proxy['rdfs:label,dcterms:title']}";
        $proxyDescription = "{$proxy['dcterms:description,rdfs:comment,po:synopsis']}";

        // find all the resources which could be useful;
        // if the proxy has olo:slot resources, we want their olo:items
        $slotItemUris = array();
        $slotResources = $proxy['olo:slot'];

        foreach($slotResources as $slotResource)
        {
            $slotResourceUri = "$slotResource";
            $slotResource = $lod[$slotResourceUri];
            $slotItemUris[] = "{$slotResource['olo:item']}";
        }

        // fetch the slot resources; we need these to be able to get the
        // players
        $lod->fetchAll($slotItemUris);

        // if the format is RDF, return the whole LOD object as Turtle
        // (mostly useful for dev)
        if($format === 'rdf')
        {
            return Rdf::toTurtle($lod);
        }
        else
        {
            // convert relevant resources to JSON
            $pages = array();
            $players = array();
            $content = array();

            // extract web pages, only from the proxy itself
            if($media === 'text')
            {
                foreach($proxy['foaf:page'] as $page)
                {
                    $pageUri = "{$page->value}";

                    // ignore non-HTTP URIs
                    if(substr($pageUri, 0, 4) === 'http')
                    {
                        $pages[] = array(
                            'source_uri' => $proxyUri,
                            'uri' => $pageUri,
                            'label' => $proxyLabel,
                            'mediaType' => 'web page'
                        );
                    }
                }
            }

            // extract players and content via olo:slot->olo:item
            foreach($slotItemUris as $slotItemUri)
            {
                // retrieve the URIs of the media which are same as the slot item
                // ($slotItemUri is a RES proxy URI, so this gives us the URI
                // of the original resource)
                $sameAsSlotItemUris = $lod->getSameAs($slotItemUri);

                // also get the topics or primary topics of the resources which
                // are sameAs the slot item
                $topicUris = array();
                foreach($sameAsSlotItemUris as $sameAsSlotItemUri)
                {
                    $sameAsResource = $lod[$sameAsSlotItemUri];
                    $topicUris[] = "{$sameAsResource['foaf:topic,foaf:primaryTopic,schema:about']}";
                }

                $possibleMediaUris = array_merge($sameAsSlotItemUris, $topicUris);
                foreach($possibleMediaUris as $possibleMediaUri)
                {
                    // we don't get any date statements unless we fetch the
                    // resource; but fetching it serially is too slow; so for
                    // now, just resolve using the graph we already have
                    $resource = $lod[$possibleMediaUri];

                    if(empty($resource))
                    {
                        continue;
                    }

                    // if it's got an mrss:player or mrss:content, we want it;
                    // but we reject any resources which don't match the media
                    // type filter (if set)
                    foreach($resource['mrss:player,mrss:content'] as $mediaUri)
                    {
                        $mediaType = getMediaType($resource);

                        if($mediaType && ($mediaType !== $media))
                        {
                            continue;
                        }

                        $players[] = array(
                            'source_uri' => $possibleMediaUri,
                            'uri' => "$mediaUri",
                            'mediaType' => $mediaType,
                            'label' => "{$resource['dcterms:title,rdfs:label']}",
                            'description' => "{$resource['dcterms:description,rdfs:comment']}",
                            'thumbnail' => "{$resource['schema:thumbnailUrl']}",
                            'date' => "{$resource['dcterms:date']}",
                            'location' => "{$resource['lio:location']}"
                        );
                    }
                }
            }

            return array(
                'uri' => "$proxyUri",
                'label' => $proxyLabel,
                'description' => $proxyDescription,
                'media' => $media,
                'players' => $players,
                'content' => $content,
                'pages' => $pages
            );
        }
    }
}
