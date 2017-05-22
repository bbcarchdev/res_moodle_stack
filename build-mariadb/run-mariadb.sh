#!/bin/bash
set -e

chown -R mysql:mysql /var/lib/mysql

mkdir -p /var/run/mysqld
chown -R mysql:mysql /var/run/mysqld

MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-""}
MYSQL_DATABASE=${MYSQL_DATABASE:-""}
MYSQL_USER=${MYSQL_USER:-""}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-""}

tfile=/tmp/bootstrap.sql

cat << EOF > $tfile
USE mysql;
FLUSH PRIVILEGES;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
ALTER USER 'root'@'localhost' IDENTIFIED BY "$MYSQL_ROOT_PASSWORD";
EOF

if [[ $MYSQL_DATABASE != "" ]]; then
    echo "CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\` CHARACTER SET utf8 COLLATE utf8_general_ci;" >> $tfile

    if [[ $MYSQL_USER != "" ]]; then
        echo "GRANT ALL ON \`$MYSQL_DATABASE\`.* to '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';" >> $tfile
    fi
fi

/usr/bin/mysql_ssl_rsa_setup

/usr/sbin/mysqld --user=mysql --explicit_defaults_for_timestamp --init-file=$tfile
