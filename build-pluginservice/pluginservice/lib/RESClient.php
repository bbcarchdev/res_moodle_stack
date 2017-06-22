<?php
namespace res\libres;

require_once(__DIR__ . '/../vendor/autoload.php');

use res\liblod\LOD;
use res\liblod\Rdf;
use res\libres\RESMedia;
use res\libres\RESLicence;

/* Client for RES */
class RESClient
{
    private $acropolisUrl;

    public function __construct($acropolisUrl, $lod=NULL)
    {
        $this->acropolisUrl = $acropolisUrl;

        if(empty($lod))
        {
            $lod = new LOD();
        }
        $this->lod = $lod;
    }

    /*
     * Search RES for topics with related media.
     * $media is one of 'audio', 'image', 'text' or 'video'
     * $audiences is an array of recognised Acropolis audience URIs
     */
    public function search($query, $media, $limit=10, $offset=0, $audiences=NULL)
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

            if(is_array($audiences))
            {
                $audiencesQuery = '';
                foreach($audiences as $audience)
                {
                    if($audiencesQuery !== '')
                    {
                        $audiencesQuery .= '&';
                    }
                    $audiencesQuery .= 'for=' . urlencode($audience);
                }

                $uri .= '&' . $audiencesQuery;
            }

            $result['acropolis_uri'] = $uri;

            $searchResultResource = $this->lod[$uri];

            foreach($searchResultResource['olo:slot'] as $slot)
            {
                $slotUri = $slot->value;
                $slotResource = $this->lod[$slotUri];

                // if we can't resolve the slot resource, don't do anything
                if(!$slotResource)
                {
                    continue;
                }

                foreach($slotResource['olo:item'] as $slotItem)
                {
                    if($slotItem->isResource())
                    {
                        $topic = $this->lod[$slotItem->value];

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
        $proxy = $this->lod->fetch($proxyUri);

        if(!$proxy)
        {
            return ($format === 'json' ? NULL : '');
        }

        // find all the resources which could be useful;
        // if the proxy has olo:slot resources, we want their olo:items
        $slotItemUris = array();
        $slotObjectResources = $proxy['olo:slot'];

        foreach($slotObjectResources as $slotObjectResource)
        {
            $slotResourceUri = "$slotObjectResource";
            $slotResource = $this->lod[$slotResourceUri];
            $slotItemUris[] = "{$slotResource['olo:item']}";
        }

        // fetch the slot resources; we need these to be able to get the
        // players
        $this->lod->fetchAll($slotItemUris);

        // if the format is RDF, return the whole LOD object as Turtle
        // (mostly useful for dev)
        if($format === 'rdf')
        {
            return Rdf::toTurtle($this->lod);
        }
        else
        {
            $proxyLabel = "{$proxy['rdfs:label,dcterms:title']}";
            $proxyDescription = "{$proxy['dcterms:description,rdfs:comment,po:synopsis']}";

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
                $sameAsSlotItemUris = $this->lod->getSameAs($slotItemUri);

                // also get the topics or primary topics of the resources which
                // are sameAs the slot item
                $topicUris = array();
                $topicPredicates = 'foaf:topic,foaf:primaryTopic,schema:about';
                $licensePredicates = 'cc:license,dcterms:license,' .
                                     'dcterms:rights,dcterms:accessRights,' .
                                     'xhtml:license';

                foreach($sameAsSlotItemUris as $sameAsSlotItemUri)
                {
                    $sameAsResource = $this->lod[$sameAsSlotItemUri];
                    $topicUris[] = "{$sameAsResource[$topicPredicates]}";
                }

                $possibleMediaUris = array_merge($sameAsSlotItemUris, $topicUris);
                foreach($possibleMediaUris as $possibleMediaUri)
                {
                    // we don't get any date statements unless we fetch the
                    // resource; but fetching it serially is too slow; so for
                    // now, just resolve using the graph we already have
                    $resource = $this->lod[$possibleMediaUri];

                    if(empty($resource))
                    {
                        continue;
                    }

                    // if it's got an mrss:player or mrss:content, we want it;
                    // but we reject any resources which don't match the media
                    // type filter (if set)
                    foreach($resource['mrss:player,mrss:content'] as $mediaUri)
                    {
                        $mediaType = RESMedia::getMediaType($resource);

                        if($mediaType === $media)
                        {
                            $licence = "{$resource[$licensePredicates]}";
                            if(!empty($licence))
                            {
                                $licence = RESLicence::getShortForm($licence);
                            }

                            $players[] = array(
                                'sourceUri' => $possibleMediaUri,
                                'uri' => "$mediaUri",
                                'mediaType' => $mediaType,
                                'license' => $licence,
                                'label' => "{$resource['dcterms:title,rdfs:label']}",
                                'description' => "{$resource['dcterms:description,rdfs:comment']}",
                                'thumbnail' => "{$resource['schema:thumbnailUrl']}",
                                'date' => "{$resource['dcterms:date']}",
                                'location' => "{$resource['lio:location']}"
                            );
                        }
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
