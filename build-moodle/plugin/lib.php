<?php
/**
 * @package   repository_res
 * @copyright 2017, Elliot Smith <elliot.smith@bbc.co.uk>
 * @license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0
 */
global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

class repository_res extends repository {
    private $pluginservice_url;

    public function __construct($repositoryId, $context=SYSCONTEXTID,
    $options=array(), $readonly=0)
    {
        parent::__construct($repositoryId, $context, $options, $readonly);
    }

    public static function plugin_init()
    {
        $options = array(
            'name' => 'RES',
            'pluginservice_url' => getenv('PLUGINSERVICE_URL')
        );

        $id = repository::static_function('res','create', 'res', 0,
                                          context_system::instance(),
                                          $options, 0);

        return !empty($id);
    }

    public static function get_instance_option_names()
    {
        $option_names = array('pluginservice_url');
        return array_merge(parent::get_instance_option_names(), $option_names);
    }

    public static function instance_config_form($mform, $classname='repository_res')
    {
        parent::instance_config_form($mform, 'repository_res');

        // name
        $mform->setDefault('name', 'RES');

        // pluginservice_url
        $mform->addElement('text', 'pluginservice_url',
                           get_string('pluginservice_url', 'repository_res'),
                           array('size' => '60'));
        $mform->setType('pluginservice_url', PARAM_URL);
        $mform->setDefault('pluginservice_url', getenv('PLUGINSERVICE_URL'));
        $mform->addRule('pluginservice_url', get_string('required'),
                        'required', null, 'client');
    }

    public function get_listing($path=null, $page=null)
    {
        // load external filepicker
        $callback_url = new moodle_url('/') .
                        'repository/res/callback.php?repo_id=' . $this->id;

        $pluginservice_url = $this->get_option('pluginservice_url') .
                             '?callback=' . urlencode($callback_url);

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
