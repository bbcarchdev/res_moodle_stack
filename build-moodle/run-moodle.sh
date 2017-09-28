#!/bin/bash

# copyright BBC 2017
# author    Elliot Smith <elliot.smith@bbc.co.uk>
# license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0

# Wait for mysql
until nc -z ${MYSQL_HOST} 3306; do
    echo "$(date) - waiting for mysql..."
    sleep 2
done

# Adjust the configuration on first run
php /check-database.php

if [ $? -eq 1  ] ; then
    echo "Initialising Moodle.."

    # Set up Moodle using Moodle admin tools
    php /var/www/html/admin/cli/install_database.php \
        --adminuser=admin --adminpass=admin --adminemail=admin@localhost.local \
        --agree-license --fullname=Research\ and\ Education\ Space --shortname=RES

    php /var/www/html/admin/tool/generator/cli/maketestcourse.php \
        --shortname=TestCourse --size=S
else
    echo "Moodle already initialised"
fi

# Run the requested command
exec "$@"

