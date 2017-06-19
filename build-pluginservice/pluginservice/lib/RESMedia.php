<?php
namespace res\libres;

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

/* Class for working with RES media types */
class RESMedia
{
    /* returns media type if the LODInstance $lodinstance has rdf:type <media type>,
       where <media type> is a recognisable media RDF type; NULL otherwise;
       media type is one of
       'audio', 'video', 'image', 'text' */
    public static function getMediaType($lodinstance)
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
}
