<?php

namespace Celery;

/**
 * A container for arbitrary configuration for instances of
 * the Celery class. Meant to reduce the 'parameter bloat'
 * of the Celery::__construct() method.
 */
class Config
{

	/**
	 * @var array
	 *
	 * Configuration values. All possible configuration keys
	 * should be listed here and have a default value defined,
	 * so it is apparent what parameters for the Celery instance
	 * can be modified.
	 */
	protected $config = [
		// Whether to delete messages from queue
		// after they were _successfully_ returned.
		'keep_messages_in_queue' => false,
	];

	public function __construct(array $config = [])
	{
		$this->config = array_merge($this->config, $config);
	}

	public function __get($name)
	{
		if (!array_key_exists($name, $this->config)) {
			throw new \LogicException("Config parameter '$name' not set");
		}

		return $this->config[$name];
	}

	public function __set($name, $value)
	{
		throw new \LogicException("Cannot modify already created " . __CLASS__ . " object");
	}

}
