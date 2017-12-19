from celery.task import task
import time


@task
def add(x, y):
    print("Got add()")
    return x + y


@task
def add_delayed(x, y):
    print("Got add_delayed()")
    time.sleep(1)
    print("Woke up from add_delayed()")
    return x+y


@task
def fail():
    print("Got fail()")
    raise Exception


@task
def delayed():
    print("Got delayed()")
    time.sleep(2)
    print("Woke up from delayed()")
    return 'Woke up'


@task
def get_fibonacci():
    return [1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144]


@task(bind=True)
def long_running_with_progress(self):
    print("Start long running with progress.")
    self.update_state(state="PROGRESS", meta={"progress": 5})
    print("5")
    time.sleep(0.1)
    self.update_state(state="PROGRESS", meta={"progress": 20})
    print("20")
    # Intentionally too much - to simulate a long delay between two
    # meta data updates.
    time.sleep(20)
    self.update_state(state="PROGRESS", meta={"progress": 40})
    print("40")
    time.sleep(1)
    self.update_state(state="PROGRESS", meta={"progress": 60})
    print("60")
    time.sleep(1)
    self.update_state(state="PROGRESS", meta={"progress": 80})
    print("80")
    time.sleep(1)
    print("Done.")
