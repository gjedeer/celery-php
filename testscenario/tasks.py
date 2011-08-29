#!/usr/bin/python
from celery.task import task
import time

@task
def add(x, y):
    return x + y

@task 
def add_delayed(x, y):
    time.sleep(1)
    return x+y

@task
def fail():
    return fffffff

@task
def delayed():
    time.sleep(2)
    return 'Woke up'
