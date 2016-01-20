<?php defined('_JEXEC') or die;

class TriD
{
	public static function _($mixed = null, $dump = false, $trace = true)
	{
		PlgSystemTriDebug::dump($mixed, $dump, $trace);
	}
}

class PlgSystemTriDebug extends JPlugin
{
	private static $isEnabled   = true;
	private static $printTraces = true;
	private static $dumps       = [];
	private static $traces      = [];

	private $level;
	private $errors;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->level  = E_ALL;
		$this->errors = [];


		$file = '/tmp/tridebug_'.str_replace('/', '_', JPATH_BASE);
		if (!is_file($file)) {
			error_log('TO ENABLE DEBUG PLUGIN: touch '.$file);
			self::$isEnabled = false;
			return;
		}

		$doc = JFactory::getDocument();
		$doc->addStyleDeclaration('
			#tri-debug { border-top: 10px solid red; color: #000000; background; #ffffff; padding: 20px 20px 100px 20px; }
			#tri-debug-content > div:not(.separator) { display: table; border-collapse: collapse; margin: 0 0 20px 0; }
			#tri-debug-content > div:not(.dumps) > div { display: table-row; }
			#tri-debug-content > div:not(.dumps) > div:hover { background: #dddddd; }
			#tri-debug-content > div:not(.dumps) > div > div { display: table-cell; border: 1px solid #cccccc; padding: 2px 4px; }
			#tri-debug-content > div:not(.dumps) > div > div.args { white-space: pre; }
			#tri-debug-content > div:not(.dumps) > div > div.index,
			#tri-debug-content > div:not(.dumps) > div > div.line { text-align: right; }
			#tri-debug-content div.separator { border-bottom: 2px dotted red; width: 100%; height: 1px; }
		');

		ini_set('display_errors', '1');
		error_reporting($this->level);
		set_error_handler([$this, 'handler']);

		ob_start();
		ob_implicit_flush(false);
	}

	public function onAfterRespond()
	{
		if (!self::$isEnabled) {
			return;
		}
		$contents = ob_get_contents();

		if ($contents) {
			ob_end_clean();
		}
		$html   = [];
		$js     = [];
		$html[] = '<div id="tri-debug">';
		$html[] = '<h1>Debug</h1>';
		$html[] = ' <div id="tri-debug-content">';
		if (sizeof(self::$dumps)) {
			$html[] = '  <h2>Dumps</h2>';
			foreach(self::$dumps as $dump) {
				$html[] = '  <pre class="dump">'.trim($dump->dump).'</pre>';
				if ($dump->trace) {
					$html[] = '  <div class="traces">';
					foreach($dump->trace as $key => $fn) {
						if ($key < 1) { // skip debug function calls
							continue;
						}

						$args   = sizeof($args) ? preg_replace('/^\n/', '', preg_replace('/\n\)$/', '', str_replace("Array\n(", '', print_r($fn['args'], 1)))) : '';

	//					$js[]   = '   console.log("PHP: '.$fn->code.': '.$fn->msg.'\n     '.$fn->file.' on line '.$fn->line.'");';
						$html[] = '   <div class="trace">';
						$html[] = '    <div class="index">'.$key.'</div>';
						$html[] = '    <div class="file">'.$fn['file'].'</div>';
						$html[] = '    <div class="line">'.$fn['line'].'</div>';
						$html[] = '    <div class="class">'.(array_key_exists('class', $fn) ? $fn['class'] : '').'</div>';
						$html[] = '    <div class="type">'.(array_key_exists('type', $fn) ? $fn['type'] : '').'</div>';
						$html[] = '    <div class="function">'.$fn['function'].'</div>';
						$html[] = '    <div class="args">'.$args.'</div>';
						$html[] = '   </div>';
					}
					$html[] = '  </div>';
				}
			$html[] = '  <div class="separator"></div>';
			}
		}

		if (sizeof($this->errors)) {
			$html[] = '  <h2>Errors</h2>';
			$html[] = '  <div class="errors">';
			foreach($this->errors as $key => $error) {
				$js[]   = '   console.log("PHP: '.$error->code.': '.$error->msg.'\n     '.$error->file.' on line '.$error->line.'");';
				$html[] = '   <div class="error">';
				$html[] = '    <div class="index">'.$key.'</div>';
				$html[] = '    <div class="code">'.$error->code.'</div>';
				$html[] = '    <div class="msg">'.htmlspecialchars($error->msg).'</div>';
				$html[] = '    <div class="file">'.$error->file.'</div>';
				$html[] = '    <div class="line">'.$error->line.'</div>';
				$html[] = '   </div>';
			}
			$html[] = '  </div>';
		}

		$html[] = '  <script type="text/javascript">';
		$html[] = '   if (!console || typeof console.log != "function") { console = { log: function(msg) {} }; };';
		$html[] = implode("\n", $js);
		$html[] = '  </script>';
		$html[] = ' </div>';
		$html[] = '</div>';


		/// TODO: different doc types
		echo str_replace('</body>', implode("\n", $html) . '</body>', $contents);
	}

	public function handler($code, $msg, $file, $line)
	{
		if (!($this->level & $code)) {
			return;
		}

		$file = str_replace(JPATH_BASE, '', $file);

		$this->errors[] = (object)['code' => self::getErrorName($code), 'msg' => $msg, 'file' => $file, 'line' => $line];

		return true; // do not call other handlers
	}

	public static function dump($mixed = null, $dump = false, $trace = true)
	{
		if (!self::$isEnabled) {
			return;
		}
		if (!is_array(self::$dumps)) {
			self::$dumps = array();
		}
		$tmp = (object)['dump' => '', 'trace' => []];

		if ($dump || $mixed === null || $mixed === 0 || $mixed === false) {
			ob_start();
			var_dump($mixed);
			$tmp->dump = ob_get_contents();
			ob_end_clean();
		} else {
			$tmp->dump = print_r($mixed, true);
		}

		if (self::$printTraces && $trace) {
			$tmp->trace = debug_backtrace(0);
		}

		self::$dumps[] = $tmp;
	}

	public static function getErrorName($code)
	{
		switch($code) {
			case  E_ERROR:             return 'E_ERROR';
			case  E_WARNING:           return 'E_WARNING';
			case  E_PARSE:             return 'E_PARSE';
			case  E_NOTICE:            return 'E_NOTICE';
			case  E_CORE_ERROR:        return 'E_CORE_ERROR';
			case  E_CORE_WARNING:      return 'E_CORE_WARNING';
			case  E_COMPILE_ERROR:     return 'E_COMPILE_ERROR';
			case  E_COMPILE_WARNING:   return 'E_COMPILE_WARNING';
			case  E_USER_ERROR:        return 'E_USER_ERROR';
			case  E_USER_WARNING:      return 'E_USER_WARNING';
			case  E_USER_NOTICE:       return 'E_USER_NOTICE';
			case  E_STRICT:            return 'E_STRICT';
			case  E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
			case  E_DEPRECATED:        return 'E_DEPRECATED';
			case  E_USER_DEPRECATED:   return 'E_USER_DEPRECATED';
		}

		return 'UNKNOWN';
	}
}

