#!/bin/sh

# Based on http://www.php.net/manual/en/amqp.installation.php
#
# Required packages (debian):
sudo apt-get install git php5-dev make gcc autoconf pkg-config

# Install librabbitmq-c
git clone https://github.com/alanxz/rabbitmq-c.git
cd rabbitmq-c
git submodule init
git submodule update
autoreconf -i && ./configure && make && sudo make install

# Install and compile PHP extension
sudo pecl install amqp

# Problem?
# Make sure php.ini is loading amqp.so
# Test with php -i | grep amqp
