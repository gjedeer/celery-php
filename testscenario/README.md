## Setting up

	rabbitmqctl add_user gdr test
	rabbitmqctl add_vhost wutka
	rabbitmqctl set_permissions -p wutka gdr ".*" ".*" ".*"

## Running

	cd testscenario
	celeryd
	# In another terminal
	phpunit CeleryTest unittest.php
