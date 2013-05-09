BROKER_URL = "amqp://gdr:test@localhost:5672/wutka"

CELERY_RESULT_BACKEND = "amqp"

CELERY_IMPORTS = ("tasks", )

CELERY_RESULT_SERIALIZER = "json"
CELERY_TASK_RESULT_EXPIRES = None
