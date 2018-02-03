#!/bin/bash

#first download composer
mkdir composer
cd composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

#make composer effect
mv composer.phar /usr/local/bin/composer
cd ..
rm -rf composer

#install rely
apt-get install  php-zip

#install lavarel
mkdir laravel
cd laravel
composer global require "laravel/installer"

cd ..
rm -rf lavarel

#set lavarel effect
export PATH=$HOME"/.config/composer/vendor/bin:"$PATH

##install rely
apt-get install php-xml
apt-get install php-mbstring
 
#make a new project orait
cd ../../server/backend
laravel new orait