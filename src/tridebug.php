<?php defined('_JEXEC') or die;

require_once 'debug.php';

class PlgSystemTriDebug extends JPlugin
{
	private $debug;

	public function __construct(&$subject, $config)
	{
		$this->debug = \Triiuark\D::getInstance((object) ['path' => JPATH_BASE]);

		if (array_key_exists('triiuarkDebugLogJoomla', $_REQUEST) && $_REQUEST['triiuarkDebugLogJoomla'])
		{
			$options = ['logger' => 'callback', 'callback' => [$this, 'handler']];
			JLog::addLogger($options);
		}

		parent::__construct($subject, $config);
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

