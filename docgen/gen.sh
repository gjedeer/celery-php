#!/bin/sh

cd /home/gdr/celery-php
git pull

phpdoc -o HTML:Smarty:HandS -f /home/gdr/celery-php/celery.php -t /srv/celery-php-doc --sourcecode on
