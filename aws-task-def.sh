#!/bin/bash
# wrapper for ecs-cli command to create task definition

# START: EDIT THESE VARIABLES
export MOODLE_PLUGIN_IMAGE=075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_moodle
export MARIADB_IMAGE=075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_mariadb
export PLUGINSERVICE_IMAGE=075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_pluginservice

export MOODLE_URL=http://ec2-34-250-205-153.eu-west-1.compute.amazonaws.com
export PLUGINSERVICE_URL=http://ec2-34-250-205-153.eu-west-1.compute.amazonaws.com:8888/minimal.html

export MYSQL_DATABASE=moodle
export MYSQL_USER=moodle
export MYSQL_PASSWORD=moodle
export MYSQL_ROOT_PASSWORD=moodle
# END: EDIT THESE VARIABLES

# create the task definition
ecs-cli compose -f docker-compose.dist.yml -p res-moodle-plugin-task create
