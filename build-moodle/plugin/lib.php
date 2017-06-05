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

        // TODO make this configurable in the plugin
        $this->pluginservice_url = getenv('PLUGINSERVICE_URL');

        $this->moodle_url = getenv('MOODLE_URL');
    }

    public function get_listing($path=null, $page=null)
    {
        // load external filepicker
        $callback_url = rtrim($this->moodle_url, '/');
        $callback_url .= '/repository/res/callback.php?repo_id=' . $this->id;

        $pluginservice_url = $this->pluginservice_url . '?callback=' . urlencode($callback_url);

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
