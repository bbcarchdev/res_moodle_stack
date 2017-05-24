#!/bin/bash

read pid cmd state ppid pgrp session tty_nr tpgid rest < /proc/self/stat
trap "kill -TERM -$pgrp; exit" EXIT TERM KILL SIGKILL SIGTERM SIGQUIT

rm -f /var/run/apache2/apache2.pid

source /etc/apache2/envvars
tail -F /var/log/apache2/* &
exec apache2 -D FOREGROUND
