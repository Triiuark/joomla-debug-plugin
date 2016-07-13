<?php defined('_JEXEC') or die;

require_once 'd.php';

class PlgSystemTriDebug extends JPlugin
{
	private $debug;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$params = (object) [
			'path'            => JPATH_BASE,
			'level'           => E_ALL,
			'printSuppressed' => (bool)$this->params->get('printSuppressed', false),
			'printTraces'     => (bool)$this->params->get('printTraces', true),
			'oneTmpFile'      => (bool)$this->params->get('oneTmpFile', false)
		];
		$this->debug = \Triiuark\D::getInstance($params);

		if ($this->params->get('printJoomla', false))
		{
			$options = ['logger' => 'callback', 'callback' => [$this, 'handler']];
			JLog::addLogger($options);
		}
	}

	public function handler($arg)
	{
		if ($arg instanceof JLogEntry)
		{
			$msg = 'Joomla '.$arg->category.': '.$arg->date->format('H:i:s T'). ' - '.$arg->message;
			trigger_error($msg);
			\Triiuark\D::_($msg);
		}
		else
		{
			\Triiuark\D::_($arg);
		}
	}
}

