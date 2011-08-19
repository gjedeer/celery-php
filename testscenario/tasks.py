#!/usr/bin/python
from celery.task import task

@task
def add(x, y):
    return x + y

@task
def fail():
    return fffffff
