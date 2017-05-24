# Moodle plugin

See https://github.com/moodle/moodle/tree/master/repository/wikimedia for the
official Wikimedia plugin, which this is vaguely based on.

## Development

For development purposes, you can run up a Moodle instance running on
Apache+MySQL with:

    docker-compose build
    docker-compose up

Moodle will be accessible at http://localhost:8080 or via SSL at
https://localhost:8443.

Admin username/password: `admin/admin`

## Licence

Apache v2

Lightbulb icon from https://octicons.github.com/, released under the SIL OFL
(http://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=OFL).
