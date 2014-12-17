#!/usr/bin/python
from celery.task import task
import time

@task
def add(x, y):
    print "Got add()"
    return x + y

@task 
def add_delayed(x, y):
    print "Got add_delayed()"
    time.sleep(1)
    print "Woke up from add_delayed()"
    return x+y

@task
def fail():
    print "Got fail()"
    return fffffff

@task
def delayed():
    print "Got delayed()"
    time.sleep(2)
    print "Woke up from delayed()"
    return 'Woke up'

@task
def get_fibonacci():
    return [1,1,2,3,5,8,13,21,34,55,89,144]
