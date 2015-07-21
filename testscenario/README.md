## Setting up

	rabbitmqctl add_user gdr test
	rabbitmqctl add_vhost wutka
	rabbitmqctl set_permissions -p wutka gdr ".*" ".*" ".*"

## Running

	cd testscenario
	celery worker -l DEBUG -c 20

	# In another terminal
	cd testscenario
	celery worker -l DEBUG -c 20 --config=celeryredisconfig

	# In another terminal
	phpunit unittest

## Testing Redis authentication
	echo requirepass test >>/etc/redis/redis.conf
	service redis-server restart

	cd testscenario
	celery worker -l DEBUG -c 20 --config=celeryredisauthconfig

	# In another terminal
	phpunit CeleryRedisAuthTest unittest/CeleryRedisAuthTest.php
