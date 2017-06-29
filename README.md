# RES Moodle stack

This is a self-contained stack for deploying Moodle (running on Apache),
MariaDB (with the Moodle database) and a RES search app (which can be
accessed via a plugin in the Moodle instance). It runs standalone
on a single machine, or can be deployed to Amazon Web Services (AWS).

The stack uses [Docker](https://www.docker.com/) to build the images
and [docker-compose](https://docs.docker.com/compose/overview/) to run them
in such a way that they can talk to each other.

## Development

To run the stack for development purposes, you need to ensure that the following
domain names resolve to 127.0.0.1 on your machine:

    pluginservice
    moodle

This can be done by editing your hosts file (e.g. /etc/hosts on a Linux
machine).

You also need the following software to build the images:

* [Composer](https://getcomposer.org/download/)
* [Docker](https://www.docker.com/get-docker)
* [docker-compose](https://docs.docker.com/compose/install/)

You can then run a Moodle instance on Apache + MariaDB with:

    git submodule init
    git submodule update
    cd build-pluginservice/pluginservice
    composer install
    cd ../..
    docker-compose up --build

Moodle will be accessible at http://moodle. Admin username/password:
`admin/admin`.

The RES search service will be at http://pluginservice:8888.

The Moodle plugin can be accessed as follows:

* Go to the test course (set up by default in the Docker image).
* Login with admin/admin.
* Select "Turn editing on" for the course.
* Click on "Add activity or resource".
* Select "URL" from the pop-up (right at the bottom on the left) and click "Add".
* Click the "Choose a link" button, then select "RES" from the list of available plugins.

## Deployment

This stack can be deployed to AWS with a small amount of pain, as explained
below.

### Set up AWS access

You will need our AWS admin to set up an account and permissions for you.

Install and configure AWS command line tools (these require Python):

    pip install awscli

Configure AWS:

    aws configure

Follow the prompts:

    AWS Access Key ID [None]: <YOUR ACCESS_KEY>
    AWS Secret Access Key [None]: <YOUR SECRET_KEY>
    Default region name [None]: eu-west-1 (or your default region if different)
    Default output format [None]:

### Create Docker ECR registries and push RES Moodle stack images

Create an ECS Container repository to store each docker image into. I did this via the web console (https://eu-west-1.console.aws.amazon.com/ecs/home?region=eu-west-1#/repositories) and ended up with these repositories:

    075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_moodle
    075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_mariadb
    075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_pluginservice

Get the login for the container registry:

    aws ecr get-login --region eu-west-1

Copy the output command and run it; it looks like this:

    docker login -u AWS -p ...verylongstring... -e none https://075239016712.dkr.ecr.eu-west-1.amazonaws.com

Build the images:

    docker-compose build

Tag the images:

    docker tag res-moodle-plugin_moodle 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_moodle
    docker tag res-moodle-plugin_mariadb 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_mariadb
    docker tag res-moodle-plugin_pluginservice 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_pluginservice

(Note the tag for the image includes the domain name you got when you called `aws ecr get-login` above.)

Push them to the container registry:

    docker push 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_moodle
    docker push 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_mariadb
    docker push 075239016712.dkr.ecr.eu-west-1.amazonaws.com/res-moodle-plugin_pluginservice

### Deploy Moodle and plugin service to EC2 using docker-compose

The Docker images can be deployed to AWS using the ECS CLI tool (http://docs.aws.amazon.com/AmazonECS/latest/developerguide/ECS_CLI_Configuration.html), which supports docker-compose.

Install ecs-cli as per the instructions.

(For the following parts, I followed the tutorial at http://docs.aws.amazon.com/AmazonECS/latest/developerguide/ECS_CLI_tutorial.html.)

Configure your access credentials:

    ecs-cli configure --region eu-west-1 --access-key $AWS_ACCESS_KEY --secret-key $AWS_SECRET_KEY --cluster res-moodle-plugin

Create the cluster:

    ecs-cli up --keypair $SSH_KEY_PAIR --capability-iam --size 1 --instance-type t2.micro --vpc vpc-48e2c02c --subnets subnet-f5b38583,subnet-90f9cbf4

`$SSH_KEY_PAIR` should be the name of a key pair registered for ECS (I found this under https://eu-west-1.console.aws.amazon.com/ec2/v2/home?region=eu-west-1#KeyPairs). The `--vpc` and `--subnets` values came from my AWS admin.

(NB if you import a public key at this point, you should remove the BEGIN...END lines when you do the import.)

The docker-compose config for deployment is in `docker-compose.dist.yml`. For demo purposes, a single instance can be used to host all of the images; this is why a `mem_limit` setting is in the config, to ensure that the individual images get the memory they need. If a larger instance is being used, this should be modified to use as much of the available memory as possible.

Get the domain name for the instance (via the EC2 console), then edit the variables at the top of the `aws-task-def.sh` script to match this and the names of your repositories.

Create a task definition which is going to run on the cluster:

    ./aws-task-def.sh

Start the task on the cluster:

    aws ecs run-task --task-definition ecscompose-res-moodle-plugin-task --cluster res-moodle-plugin

Because the Moodle plugin needs access to port 8888 on the instance, you will have to add a new role to the instance's security group to allow this. You can do this via the EC2 Management Console.

1. View instances at https://eu-west-1.console.aws.amazon.com/ec2/v2/home?region=eu-west-1#Instances:sort=instanceId.
2. Select the instance running the Moodle plugin and friends. In the bottom panel of the screen, find the "Security groups" section, which shows the main security group for the instance. Make a note of it.
3. Select "Security Groups" in the left-hand menu.
4. In the list, find the security group for the instance you made a note of earlier and select it.
5. In the bottom panel, click on the "Inbound" tab.
6. Click "Edit", then "Add Rule".
7. Choose "Custom TCP" and enter "8888" for the port range, then save.

(You can follow a similar process to add a rule to allow you to SSH to the instance if you need it.)

Moodle should now be available at `http://<instance domain name>`.

## Licence

Apache v2

Lightbulb icon from https://octicons.github.com/, released under the SIL OFL
(http://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=OFL).
