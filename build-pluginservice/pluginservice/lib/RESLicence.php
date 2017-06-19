<?php
namespace res\libres;

/* Class for converting licence URIs to short text form */
class RESLicence
{
    const LICENCE_MAP = array(
        'http://creativecommons.org/publicdomain/zero/1.0/' => 'CC0 1.0',
        'http://id.loc.gov/about/' => 'LoC',
        'http://creativecommons.org/licenses/by/4.0/' => 'CC BY 4.0',
        'http://creativecommons.org/licenses/by-sa/4.0/' => 'CC BY-SA 4.0',
        'http://reference.data.gov.uk/id/open-government-licence' => 'OGL v3',
        'http://bbcarchdev.github.io/licences/dps/1.0#id' => 'BBC DPS',
        'http://creativecommons.org/licenses/by/1.0/' => 'CC BY 1.0',
        'https://creativecommons.org/licenses/by/1.0/' => 'CC BY 1.0',
        'http://creativecommons.org/licenses/by-sa/1.0/' => 'CC BY-SA 1.0',
        'https://creativecommons.org/licenses/by-sa/1.0/' => 'CC BY-SA 1.0',
        'http://creativecommons.org/licenses/by/2.5/' => 'CC BY 2.5',
        'https://creativecommons.org/licenses/by/2.5/' => 'CC BY 2.5',
        'http://creativecommons.org/licenses/by-sa/2.5/' => 'CC BY-SA 2.5',
        'https://creativecommons.org/licenses/by-sa/2.5/' => 'CC BY-SA 2.5',
        'http://creativecommons.org/licenses/by/3.0/' => 'CC BY 3.0',
        'https://creativecommons.org/licenses/by/3.0/' => 'CC BY 3.0',
        'http://creativecommons.org/licenses/by-sa/3.0/' => 'CC BY-SA 3.0',
        'https://creativecommons.org/licenses/by-sa/3.0/' => 'CC BY-SA 3.0',
        'http://creativecommons.org/licenses/by/3.0/us/' => 'CC BY 3.0 US',
        'https://creativecommons.org/licenses/by/3.0/us/' => 'CC BY 3.0 US',
        'http://creativecommons.org/licenses/by-sa/3.0/us/' => 'CC BY-SA 3.0 US',
        'https://creativecommons.org/licenses/by-sa/3.0/us/' => 'CC BY-SA 3.0 US',
        'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/' => 'OGL v3',
        'http://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/' => 'OGL v3',
        'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/2/' => 'OGL v2',
        'http://www.nationalarchives.gov.uk/doc/open-government-licence/version/2/' => 'OGL v2',
        'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/1/' => 'OGL v1',
        'http://www.nationalarchives.gov.uk/doc/open-government-licence/version/1/' => 'OGL v1',

        'http://www.ordnancesurvey.co.uk/business-and-government/licensing/' + \
        'using-creating-data-with-os-products/os-opendata.html' => 'OS OpenData',

        'http://www.ordnancesurvey.co.uk/oswebsite/opendata/licence/docs/licence.pdf' => 'OS OpenData',
        'http://opendatacommons.org/licenses/pddl/1.0/' => 'ODC PDDL v1.0',
        'http://opendatacommons.org/licenses/by/1.0/' => 'ODC-By v1.0',
        'http://opendatacommons.org/licenses/odbl/1.0/' => 'ODbL v1.0',
        'http://collection.britishmuseum.org/licensing.html' => 'CC BY-NC-SA 4.0'
    );

    public static function getShortForm($licenceUri)
    {
        if(isset(self::LICENCE_MAP[$licenceUri]))
        {
            return self::LICENCE_MAP[$licenceUri];
        }
        else
        {
            return '';
        }
    }
}
