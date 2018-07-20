# Change log

## 3.0.0 [Unreleased]
- **Removed support for PHP 5.3.**
- celery-php now uses a PSR-4 compliant namespace, `Celery`. To migrate to the
  new version, change code from `new Celery(…)` to `new \Celery\Celery(…)`.
- Now supports php-amqplib/php-amqplib for the amqplib backend as
  videlalvaro/php-amqplib is abandoned.
- Fix crash with the ampqlib backend when Celery has not yet created the
  results exchange.
