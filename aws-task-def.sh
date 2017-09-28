#!/bin/bash

# wrapper for ecs-cli command to create task definition
#
# copyright BBC 2017
# author    Elliot Smith <elliot.smith@bbc.co.uk>
# license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0

# START: EDIT THESE VARIABLES
export MOODLE_PLUGIN_IMAGE=075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_moodle
export MARIADB_IMAGE=075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_mariadb

export MOODLE_URL=http://ec2-34-250-205-153.eu-west-1.compute.amazonaws.com

export MYSQL_DATABASE=moodle
export MYSQL_USER=moodle
export MYSQL_PASSWORD=moodle
export MYSQL_ROOT_PASSWORD=moodle
# END: EDIT THESE VARIABLES

# create the task definition
ecs-cli compose -f docker-compose.dist.yml -p res-moodle-plugin-task create
