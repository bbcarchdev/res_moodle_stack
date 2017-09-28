#!/bin/bash

# copyright BBC 2017
# author    Elliot Smith <elliot.smith@bbc.co.uk>
# license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0

echo "placeholder" > /var/moodledata/placeholder
chown -R www-data:www-data /var/moodledata
chmod 777 /var/moodledata

read pid cmd state ppid pgrp session tty_nr tpgid rest < /proc/self/stat
trap "kill -TERM -$pgrp; exit" EXIT TERM KILL SIGKILL SIGTERM SIGQUIT

rm -f /var/run/apache2/apache2.pid

source /etc/apache2/envvars
tail -F /var/log/apache2/* &
exec apache2 -D FOREGROUND
