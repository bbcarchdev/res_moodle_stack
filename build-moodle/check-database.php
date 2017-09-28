<?php
/**
 * Check for the existence of the Moodle database.
 *
 * @copyright BBC 2017
 * @author    Elliot Smith <elliot.smith@bbc.co.uk>
 * @license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0
 */

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

if (empty($CFG->version)) {
  echo "Database is not yet installed.\n";
  exit(1);
} else {
  $dbmanager = $DB->get_manager();
  $schema = $dbmanager->get_install_xml_schema();

  if ($dbmanager->check_database_schema($schema)) {
    echo "Database structure is bad; may need to recreate container.\n";
    exit(1);
  }
}

// EVERYTHING IS GOING TO BE OK
echo "Database structure is ok.\n";
exit(0);
