BROKER_HOST = "localhost"
BROKER_PORT = 5672
BROKER_USER = "guest"
BROKER_PASSWORD = "guest"
BROKER_VHOST = "/"

CELERY_RESULT_BACKEND = "amqp"

CELERY_IMPORTS = ("tasks", )

CELERY_RESULT_SERIALIZER = "json"
CELERY_TASK_RESULT_EXPIRES = None
