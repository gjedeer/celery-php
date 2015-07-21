BROKER_URL = "redis://:test@localhost:6379/0"

CELERY_RESULT_BACKEND = "redis://:test@localhost:6379/0"

CELERY_IMPORTS = ("tasks", )

CELERY_RESULT_SERIALIZER = "json"
CELERY_TASK_RESULT_EXPIRES = None
