from celery import Celery
app = Celery()
app.config_from_object('celeryredisconfig')
import tasks
tasks.add.delay(2,2)
