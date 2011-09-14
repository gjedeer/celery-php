#!/bin/sh

# Based on http://www.php.net/manual/en/amqp.installation.php
#
# Required packages (debian):
sudo apt-get install mercurial php5-dev make gcc autoconf

# Install librabbitmq-c
hg clone http://hg.rabbitmq.com/rabbitmq-c
cd rabbitmq-c
hg clone http://hg.rabbitmq.com/rabbitmq-codegen codegen
autoreconf -i && ./configure && make && sudo make install

# Install and compile PHP extension
sudo pecl install amqp-beta

# Problem?
# Make sure php.ini is loading amqp.so
# Test with php -i | grep amqp
