#!/bin/bash
set -e

# Wait for mysql
until nc -z ${MYSQL_HOST} 3306; do
    echo "$(date) - waiting for mysql..."
    sleep 2
done

# Adjust the configuration on first run
if [ ! -f /.moodle-setup-done ]; then
    echo "Initialising Moodle.."

    # Set up Moodle using Moodle admin tools
    php /var/www/html/admin/cli/install_database.php \
        --adminuser=admin --adminpass=admin --adminemail=admin@localhost.local \
        --agree-license --fullname=Research\ and\ Education\ Space --shortname=RES

    php /var/www/html/admin/tool/generator/cli/maketestcourse.php \
        --shortname=TestCourse --size=S

    touch /.moodle-setup-done
fi

# Run the requested command
exec "$@"
