## Setting up

	rabbitmqctl add_user gdr test
	rabbitmqctl add_vhost wutka
	rabbitmqctl set_permissions -p wutka gdr ".*" ".*" ".*"

## Running

	cd testscenario
	celery worker -l DEBUG -c 20
	# In another terminal
	phpunit Tests
