<?php
/**
 * @package   repository_res
 * @copyright 2017, Elliot Smith <elliot.smith@bbc.co.uk>
 * @license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0
 */
global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

class repository_res extends repository {
    public function __construct($repositoryId, $context=SYSCONTEXTID,
    $options=array(), $readonly=0)
    {
        parent::__construct($repositoryId, $context, $options, $readonly);

        // TODO make this configurable
        $this->pluginservice_url = getenv('PLUGINSERVICE_URL');
    }

    public function get_listing($path=null, $page=null)
    {
        // load external filepicker
        $pluginservice_url = $this->pluginservice_url;
        error_log("Using plugin service at $pluginservice_url\n");

        return array(
            'nologin' => TRUE,
            'norefresh' => TRUE,
            'nosearch' => TRUE,
            'object' => array(
                'type' => 'text/html',
                'src' => $pluginservice_url
            )
        );
    }

    public function supported_returntypes()
    {
        return FILE_EXTERNAL;
    }
}
