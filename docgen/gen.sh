#!/bin/sh

cd /home/gdr/celery-php
git pull

php5 /usr/bin/phpdoc --title "PHP client for Celery task queue" -o HTML:Smarty:HandS -f '/home/gdr/celery-php/*.php' -t /srv/celery-php-doc --sourcecode on
cp docgen/logo.png /srv/celery-php-doc/media/
