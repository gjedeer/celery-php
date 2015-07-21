## Goals

API stability is the primary goal. Users should upgrade from the first released version and their code should work without any issues. Unit tests are present to ensure this. 

Good documentation is a secondary goal. Every class and method needs to be commented with a [PHPDocumentor](http://www.phpdoc.org/docs/latest/guides/docblocks.html) header.

Testability is a tetrary goal. If you're reporting a bug, please provide a test case.

In other words: your patch will be rejected if it implements the latest and greatest features of Celery but breaks backwards compatibility and/or lacks documentation (and/or deletes existing documentation while also breaking indentation - I've seen this before).

## Pace of development

TLDR: The pace of development is slow. Problems? Do it yourself and submit a pull request or use paid support.

* I'm using this library in production in several projects. Any bugs affecting stability will be fixed immediately.
* This library will be tested with the latest versions of Celery, PHP-AMQPLib, PECL-AMQP and PRedis at least once a year. If necessary, fixes will be made to ensure compatibility with the latest and greatest. The [README file](README.md) lists last known working versions. If you need some version supported sooner than that, [Paid support](https://massivescale.net/contact.html) is available.
* All pull requests are tested for overall code quality and passing existing unit tests. Because of the amount of work required for this, pull requests are merged rarely, in batches, 1-3 times a year.
* Issues asking for new features will not be implemented. [Paid support](https://massivescale.net/contact.html) is available if you want something developed, or do it yourself and submit a pull request.
* Issues reporting non-critical bugs may be fixed if the author has enough free time, which happens rarely. [Paid support](https://massivescale.net/contact.html) is available, the author has plenty of work time. Pull requests implementing these are welcome too.

