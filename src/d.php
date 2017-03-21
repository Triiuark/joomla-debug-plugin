<?php

namespace Triiuark;

defined('_JEXEC') or die;

class D
{
	private static $instance = null;

	private $enabled         = true;
	private $level           = E_ALL; // error level to display
	private $printSuppressed = false; // do not print suppressed errors
	private $printTraces     = true;  //  print traces
	private $oneTmpFile      = false; // keep all other requests in one file or in a file for each client
	private $path            = '';    // path to strip from file names
	private $settings        = null;  // object - to store original errror handling settings
	private $errors          = [];
	private $dumps           = [];

	public static function getInstance(\stdClass $options = null)
	{
		if (!self::$instance)
		{
			self::$instance = new D($options);
		}

		return self::$instance;
	}

	public static function _($mixed = null, $dump = false, $trace = true)
	{
		self::getInstance()->dump($mixed, $dump, $trace);
	}

	public static function getErrorName($code)
	{
		switch ($code)
		{
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

	public static function getEnableFile()
	{
		$file = sys_get_temp_dir().DIRECTORY_SEPARATOR
				.'triiuark_debug'.str_replace(['/', '\\'], '_', __DIR__);
		return $file;
	}

	public function dump($mixed = null, $dump = false, $trace = true)
	{
		$tmp = (object)['dump' => '', 'trace' => []];

		if ($dump || !$mixed) // dump if it is null or empty or false ...
		{
			ob_start();
			var_dump($mixed);
			$tmp->dump = htmlspecialchars(ob_get_contents());
			ob_end_clean();
		}

		else
		{
			$tmp->dump = htmlspecialchars(print_r($mixed, true));
		}

		if ($this->printTraces && $trace)
		{
			$tmp->trace = debug_backtrace(0);
		}

		$this->dumps[] = $tmp;
	}

	public function handler($code, $msg, $file, $line)
	{
		if (!($this->level & $code))
		{
			return;
		}

		if (!$this->printSuppressed && error_reporting() === 0)
		{
			// happens if errors suppressed with @
			return;
		}
		$file = preg_replace('#^'.$this->path.'#', '', $file);

		$error = (object)[
				'code' => self::getErrorName($code),
				'msg'  => $msg,
				'file' => $file,
				'line' => $line,
				'time' => date('H:i:s T')];

		// also log errors
		error_log($error->code.': '.$error->msg.' in '.$error->file. ' on line '.$error->line);


		$this->errors[] = $error;
		return true; // do not call other handlers
	}

	public function stop()
	{
		if (!$this->enabled)
		{
			return;
		}

		ini_set('display_errors', $this->settings->display_errors);
		error_reporting($this->settings->level);
		if ($this->settings->handler)
		{
			@set_error_handler($this->settings->handler);
		}

		$this->enabled = false;

		$contents = ob_get_contents();

		if ($contents)
		{
			ob_end_clean();
		}

		$html          = [];
		$js            = [];
		$dumps         = [];
		$errors        = [];
		$otherRequests = [];

		$tmpDir  = sys_get_temp_dir().'/triiuark_debug/';
		if (!is_dir($tmpDir))
		{
			mkdir($tmpDir);
		}

		if (!$this->oneTmpFile
			&& array_key_exists('REMOTE_ADDR', $_SERVER)
			&& array_key_exists('HTTP_USER_AGENT', $_SERVER))
		{
			$tmpFile = $tmpDir.$_SERVER['REMOTE_ADDR'].'-'.sha1($_SERVER['HTTP_USER_AGENT']).str_replace('/', '_', dirname(__FILE__));
		}
		else
		{
			$tmpFile = $tmpDir.str_replace('/', '_', dirname(__FILE__));
		}

		if (is_file($tmpFile))
		{
			$otherRequests = file_get_contents($tmpFile);
			if ($otherRequests)
			{
				$otherRequests = unserialize($otherRequests);
			}

		}

		if (!sizeof($this->errors) && !sizeof($this->dumps) && !sizeof($otherRequests))
		{
			echo $contents;

			return;
		}

		$html[] = '<div id="triiuark-debug">';
		$html[] = '<h1>Debug</h1>';
		$html[] = ' <div id="triiuark-debug-content">';

		if (!is_array($otherRequests))
		{
			$otherRequests = [];
		}

		if (sizeof($otherRequests))
		{
			$html[] = '  <h2>Other Requests</h2>';
			$html[] = '  <button class="btn btn-default" type="button" onclick="TriiuarkDebug.toggle(this)">Hide</button>';
			$html[] = '  <div>';
			foreach ($otherRequests as $request)
			{
				if (sizeof($request->js))
				{
					$js[]   = '   console.log(\'PHP OHTER ERRORS: '.$request->time.'\n                  '.$request->uri.'\n                  '.$request->addr.'\n                  '.$request->client.'\')';
					$js[]   = $request->js;
					$js[]   = '   console.log(\'PHP END OTHER ERRORS\n--------------------\')';
				}
				$html[] = '   <h3>'.$request->uri.' - '.$request->time.'</h3>';
				if ($request->dumps)
				{
					$html[] = '   <h4>Dumps</h4>';
					$html[] = $request->dumps;
				}
				if ($request->errors)
				{
					$html[] = '   <h4>Errors</h4>';
					$html[] = $request->errors;
				}
			}
			$html[] = '  </div>';
		}

		if (sizeof($this->dumps))
		{
			$html[] = '  <h2>Dumps</h2>';
			foreach ($this->dumps as $dump)
			{
				$dumps[] = $this->buildDump($dump);
			}
		}
		$dumps  = implode("\n", $dumps);
		$html[] = $dumps;

		if (sizeof($this->errors))
		{
			$html[]   = '  <h2>Errors</h2>';
			$errors[] = '  <div class="errors table">';
			foreach ($this->errors as $key => $error)
			{
				$js[]     = '   console.log("PHP: '.$error->code.': '.$error->msg.'\n     '.$error->file.' on line '.$error->line.'");';
				$errors[] = '   <div class="error">';
				$errors[] = '    <div class="index">'.$key.'</div>';
				$errors[] = '    <div class="code">'.$error->code.'</div>';
				$errors[] = '    <div class="msg">'.htmlspecialchars($error->msg).'</div>';
				$errors[] = '    <div class="file">'.$error->file.'</div>';
				$errors[] = '    <div class="line">'.$error->line.'</div>';
				$errors[] = '    <div class="time">'.$error->time.'</div>';
				$errors[] = '   </div>';
			}
			$errors[] = '  </div>';
		}
		$errors = implode("\n", $errors);
		$js     = implode("\n", $js);
		$html[] = $errors;


		$html[] = '  <script type="text/javascript">';
		$html[] = '   if (!console || typeof console.log != "function") { console = { log: function(msg) {} }; };';
		$html[] = $js;
		$html[] = '  </script>';
		$html[] = ' </div>';
		$html[] = '</div>';


		$head = '
				<script type="text/javascript">
					var TriiuarkDebug = {
						toggle: function(element)
						{
							var next = element.nextElementSibling;
							if (getComputedStyle(next, null).getPropertyValue("display") == "none")
							{
								next.style.display = "block";
								element.textContent = "Hide";
							}
							else
							{
								next.style.display = "none";
								element.textContent = "Show";
							}
						}
					}
				</script>
				<style type="text/css">
					#triiuark-debug { border-top: 10px solid red; color: #000000; background; #ffffff; padding: 20px 20px 100px 20px; }
					#triiuark-debug h2 { clear: right; }
					#triiuark-debug * { font-family: monospace; }
					#triiuark-debug-content div.table { display: table; border-collapse: collapse; margin: 0 0 20px 0; width: auto; max-width: none; }
					#triiuark-debug-content div.table > div { display: table-row; }
					#triiuark-debug-content div.table > div:hover { background: #dddddd; }
					#triiuark-debug-content div.table > div > div { display: table-cell; border: 1px solid #cccccc; padding: 2px 4px; }
					#triiuark-debug-content div.table > div > div.args > div { display: none; white-space: pre; }
					#triiuark-debug-content div.table > div > div.index,
					#triiuark-debug-content div.table > div > div.line { text-align: right; }
					#triiuark-debug-content div.separator { border-bottom: 2px dotted red; }
					#triiuark-debug-content button { display: block!important; margin: 5px 0; /* float: right; clear: right; */ }
					#triiuark-debug-content button + * { clear: right; }
					#triiuark-debug-content button + div { padding: 0 10px; }
				</style>';

		if (strpos( $contents, '</body>') === false)
		{
			$fd = fopen($tmpFile, 'w');
			if ($fd)
			{
				$tmp  = new \stdClass;
				$post = '';

				if (sizeof($_POST))
				{
					$post = [];
					foreach ($_POST as $key => $value)
					{
						if (is_scalar($value) && strlen($value) < 100)
						{
							$post[] = $key.'='.$value;
						}
					}
					$post = sizeof($post) ? ' / POST: '.implode('&', $post) : '';
				}

				$tmp->uri    = $_SERVER['REQUEST_URI'].$post;
				$tmp->addr   = $_SERVER['REMOTE_ADDR'];
				$tmp->client = $_SERVER['HTTP_USER_AGENT'];
				$tmp->time   = date('H:i:s T');
				$tmp->dumps  = $dumps;
				$tmp->errors = $errors;
				$tmp->js     = $js;

				$otherRequests[]  = $tmp;

				fwrite($fd, serialize($otherRequests));
				fclose($fd);
			}
			echo $contents;
		}
		else
		{
			if (is_file($tmpFile))
			{
				unlink($tmpFile);
			}
			$contents = str_replace('</head>', "\n".$head."\n</head>", $contents);
			echo str_replace('</body>', "\n".implode("\n", $html)."\n</body>", $contents);
		}
	}

	public function __destruct()
	{
		$this->stop();
	}

	private function __construct(\stdClass $options = null)
	{
		$file = self::getEnableFile();

		if (is_file($file))
		{
			$this->enabled = true;

			if (array_key_exists('triiuarkDebugLogHint', $_REQUEST)
					&& $_REQUEST['triiuarkDebugLogHint'])
			{
				error_log('To disable Triiuark Debug: rm '.$file);
			}
		}
		else
		{
			$this->enabled = false;

			if (array_key_exists('triiuarkDebugLogHint', $_REQUEST)
					&& $_REQUEST['triiuarkDebugLogHint'])
			{
				error_log('To enable Triiuark Debug: touch '.$file);
			}

			return;
		}

		if ($options)
		{
			if (property_exists($options, 'path') && is_dir($options->path))
			{
				$this->path = $options->path;
			}

			$boolOpts = ['oneTmpFile', 'printSuppressed', 'printTraces'];
			foreach ($boolOpts as $opt)
			{
				if (property_exists($options, $opt))
				{
					$this->{$opt} = (bool)$options->{$opt};
				}
			}
			if (property_exists($options, 'level'))
			{
				$this->level = (int)$options->level;
			}
		}

		$this->settings                 = new \stdClass;
		$this->settings->display_errors = ini_set('display_errors', '1');
		$this->settings->level          = error_reporting($this->level);
		$this->settings->handler        = set_error_handler([$this, 'handler']);

		ob_start();
		ob_implicit_flush(false);
	}

	private function buildDump(\stdClass $dump)
	{
		$html   = [];
		$html[] = '  <button class="btn btn-default" type="button" onclick="TriiuarkDebug.toggle(this)">Hide</button>';
		$html[] = '  <div class="separator">';
		$html[] = '   <pre class="dump">'.trim($dump->dump).'</pre>';
		if ($dump->trace)
		{
			$html[] = '   <div class="traces table">';
			foreach ($dump->trace as $key => $fn)
			{
				if ($key < 1)
				{ // skip debug function calls
					continue;
				}

				$args     = '';
				$cnt      = sizeof($fn['args']);
				$showArgs = true;
				if ($cnt)
				{
					if ($cnt > 1 || !is_scalar($fn['args'][0]))
					{
						$showArgs = false;
					}
					$args = htmlspecialchars(preg_replace('/^\n/', '', preg_replace('/\n\)$/', '', str_replace("Array\n(", '', print_r($fn['args'], 1)))));
				}

				$file = array_key_exists('file', $fn) ? preg_replace('#^'.$this->path.'#', '', $fn['file']) : '';

				$html[] = '    <div class="trace">';
				$html[] = '     <div class="index">'.$key.'</div>';
				$html[] = '     <div class="file">'. $file.'</div>';
				$html[] = '     <div class="line">'.(array_key_exists('line', $fn) ? $fn['line'] : '').'</div>';
				$html[] = '     <div class="class">'.(array_key_exists('class', $fn) ? $fn['class'] : '').'</div>';
				$html[] = '     <div class="type">'.(array_key_exists('type', $fn) ? $fn['type'] : '').'</div>';
				$html[] = '     <div class="function">'.$fn['function'].'</div>';
				$html[] = '     <div class="args">';
				if ($showArgs)
				{
					$html[] = '      '.$args.'';
				}
				else
				{
					$html[] = '      <button class="btn btn-default" type="button" onclick="TriiuarkDebug.toggle(this)">Show</button>';
					$html[] = '      <div>'.$args.'</div>';
				}

				$html[] = '     </div>';
				$html[] = '    </div>';
			}
			$html[] = '   </div>';
		}
		$html[] = '  </div>';

		return implode("\n", $html);
	}
}
