broker_url = "pyamqp://gdr:test@localhost:5672/wutka"

result_backend = 'rpc://'

imports = ("tasks", )

task_serializer = 'json'
result_serializer = 'json'
accept_content = ['json']
timezone = 'Europe/Warsaw'
enable_utc = True

result_expires = None
