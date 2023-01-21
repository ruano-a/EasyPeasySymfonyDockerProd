# EasyPeasySymfonyDockerProd (with a mail server / traefik / https. Optionally)

# How to install the production

1) If the docker images were not built

In the host system (your server):

* Install docker

* Clone your project (if it's on git, and not already done), then go in it

* git clone https://github.com/ruano-a/EasyPeasySymfonyDockerProd.git docker

* cd docker

* cp .env.dist .env

* Replace the values in this .env file (docker/.env)

* If needed, at the root of the project, set the values in the .env (or .env.local depending on your symfony version). The values of MAILER_URL, MAILER_DSN, DATABASE_URL are automatically replaced during the build. But the lines have to exist, just say an empty value or something random.

* Put in the folder database/init.sql some sql files to init the database

* Use ./setup.sh (docker folder). It modifies the docker-compose.yml file depending on what you specified in the docker/.env file.

* In the docker folder : docker-compose build

2) Once they were built, but not started yet

* If you didn't built the images in the host server, you have to transfer them to the host.

* Make sure you're in the docker folder.

* Start the containers : docker-compose up

* If you have things to do in the container, (like symfony commands) Go in it : docker exec -it docker_php-apache_1 bash (if the name docker_php-apache_1 isn't working, check docker ps) and do what you have to. If you didn't init your database with an sql file, you'll need to run doctrine commands.

# Notes :

* The docker image contains the project, the php / js / everything files. So once the image is built, the outer project isn't needed.

* This means that if the .env parameters (at the root) are changed, either the image should be rebuilt, or the values should be changed in the container running. Changing the values in the inner .env file should be enough, but if not, change it in the env values with 'export'. If it's reboot, the values will be reset though.

* During the build, the composer install is done, and yarn install, and the yarn encore production. You don't need to do it.

* After these installs / generations are done, composer / yarn / npm are removed from the image, for a smaller size.

* The local-php-security-checker is downloaded in /usr/bin/local-php-security-checker. You (probably, it's in production, so your call) should use it in the composer scripts. It's removed after the install.

* It's been decided that fail2ban should be installed in the host, and not the images, because it usually handles ssh

* This repo will probably evolve over the time

* This repo should work out of the box for mainstream cases. But you might have to make custom changes. And you should. Remember that you can modify any file. It's your website.

* If you changed values in .env, and want to update the docker-compose.yml file without getting new https certificates, don't use setup.sh. Use (in the docker folder) ./update-yml.sh