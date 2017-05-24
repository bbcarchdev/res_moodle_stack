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
    }

    // as in the Wikimedia plugin, pervert the purpose of the login form to
    // create a full-page search form...
    public function check_login() {
        global $SESSION;

        $this->keyword = optional_param('res_keyword', '', PARAM_RAW);

        $sess_keyword = 'res_' . $this->id . '_keyword';

        if (empty($this->keyword) && optional_param('page', '', PARAM_RAW))
        {
            // this is the request for another page for the last search,
            // so retrieve the cached keyword
            if (isset($SESSION->{$sess_keyword}))
            {
                $this->keyword = $SESSION->{$sess_keyword};
            }
        }
        else if (!empty($this->keyword))
        {
            // save the search keyword in the session so we can retrieve it
            // later
            $SESSION->{$sess_keyword} = $this->keyword;
        }

        return !empty($this->keyword);
    }

    public function print_login()
    {
        // use the login form to create a search form...
        $keyword = new stdClass();
        $keyword->label = get_string('keyword', 'repository_res') . ': ';
        $keyword->type = 'text';
        $keyword->name = 'res_keyword';
        $keyword->value = '';

        return array(
            'login' => array($keyword),
            'nologin' => TRUE,
            'norefresh' => TRUE,
            'nosearch' => TRUE
        );
    }

    public function get_listing($path='', $page='')
    {
        $page = (int)$page;
        if ($page < 1)
        {
            $page = 1;
        }

        // TODO figure out whether there's a next page (set to -1) or not
        // (set to the current page number) using xhtml:next in Acropolis
        // results
        $pages = -1;

        $list = array(
            'dynload' => TRUE,
            'nologin' => TRUE,
            'norefresh' => TRUE,
            'nosearch' => TRUE,
            'pages' => $pages,
            'page' => $page
        );

        // TODO fetch from Acropolis

        // TODO populate the array of entries to show
        $list['object'] = array(
            'type' => 'text/html',
            'src' => 'http://townx.org/'
        );

        return $list;
    }

    public function supported_returntypes()
    {
        return FILE_EXTERNAL;
    }
}
