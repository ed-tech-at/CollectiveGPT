# Setup

Server is running https://www.ispconfig.org and https://www.docker.com

Copy nginx-ispconfig.conf to ISPConfig at "nginx Directives".

## NOGIT - Include your own passwords - search for NOGIT
Copy /php/pws.NOGIT.php to /php/pws.php

## DB
mariadb - see DBSETUP.sql

## nchan
in nchan-example.conf

Server for WebSockets from https://github.com/slact/nchan

## py-gpt
in docker-compose.yml

## DOCKER commands
docker-compose stop
docker-compose build
docker-compose up -d