#!/bin/bash

#default sock, not tcp
apt-get install php-fpm

##start 
php-fpm -D -R