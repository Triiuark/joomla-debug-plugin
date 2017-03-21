<?php defined('JPATH_PLATFORM') or die;

class JFormFieldTriDebugEnableHint extends JFormField
{
	protected $type      = 'TriDebugEnableHint';
	protected $file      = null;
	protected $isEnabled = false;

	public function __construct($form = null)
	{
		parent::__construct($form);

		$this->file = null;
		if (class_exists('\Triiuark\D'))
		{
			$this->file = \Triiuark\D::getEnableFile();
		}

		if (is_file($this->file))
		{
			$this->isEnabled = true;
		}
	}

	public function getLabel()
	{
		$label = 'PLG_SYSTEM_TRIDEBUG_FIELD_ENABLE_HINT_LABEL';
		if ($this->isEnabled)
		{
			$label = 'PLG_SYSTEM_TRIDEBUG_FIELD_DISABLE_HINT_LABEL';
		}

		return JText::_($label);
	}

	public function getInput()
	{
		$cmd = 'touch '.$this->file;
		if ($this->isEnabled)
		{
			$cmd = 'rm '.$this->file;
		}

		$html = JText::_('PLG_SYSTEM_TRIDEBUG_ENABLE_PLUGIN_HINT');
		if (class_exists('\Triiuark\D'))
		{
			$html = '<input type="text" value="'.htmlspecialchars($cmd).'" readonly="readonly"/>';
		}

		return $html;
	}
}
