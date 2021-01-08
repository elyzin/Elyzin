<?php

/**
 * Hypertext Markup Generator Class
 * 
 * @author effone <me@eff.one>
 * @license MIT
 * @todo Form elements, Markdown Parser
 */

namespace Elyzin\Service;

class Markup
{
	protected static $void_tags = ['area', 'base', 'br', 'col', 'hr', 'img', 'input', 'link', 'meta', 'param', 'command', 'keygen', 'source']; // HTML5 Compliant Void Tags

	/**
	 * HTML Element Generator (all pre-structured elements depend on this method)
	 *
	 * @param string $tag
	 * @param string $content
	 * @param array $arg
	 * @param boolean $multiline
	 * @param boolean $xhtml
	 * @return string
	 */
	public static function elem($tag = 'br', $content = '', $arg = array(), $multiline = true, $xhtml = false)
	{
		$decl = "";
		if (!empty($arg)) { // && !in_array($tag, ['br','hr']))
			$decl = array();
			foreach ($arg as $attrib => $value) {
				if (is_array($value)) {
					$glue = ($attrib == 'class') ? ' ' : ', ';
					$value = implode($glue, $value);
				}
				if (is_numeric($attrib)) {
					$attrib = $value;
					$value = '';
				}
				$value = (!$value) ? '' : '="' . $value . '"';
				if (!empty($attrib)) $decl[] = $attrib . $value;
			}
			$decl = empty($decl) ? '' : ' ' . implode(' ', $decl);
		}
		if (in_array($tag, self::$void_tags)) {
			$multiline = false; // Force oneline for void tags
			$startclose = $content = '';
			$endopen = $xhtml ? ' /' : ''; // XHTML compatibility, false by default
		} else {
			$startclose = '>';
			$endopen = '</' . $tag;
		}
		if (empty($content)) $multiline = false; // No content? Make it single line
		$eol = $multiline ? PHP_EOL : '';
		return '
<' . $tag . $decl . $startclose . $content . $eol . $endopen . '>';
	}

	// Select Dropdown Generator
	// Database Driven Example:
	// echo create_select(['project_client'=>BNAME.'_orgs'],['org_shortname'=>'org_fullname'],'MAFFFL','',array('select2'));
	// Preset Example:
	// echo create_select('language','language','en','',array('select2'));
	// Defined Example:
	// echo create_select('count',['one'=>'first','two'=>'second'],'two','',array('select2'));
	// -----------------------------------------------------------------------
	/**
	 * Select Dropdown Generator
	 *
	 * @param mixed $name
	 * @param mixed $values
	 * @param mixed $current
	 * @param string $id
	 * @param array $class
	 * @param mixed $disable // If not array the whole element will be disabled
	 * @param array $css
	 * @param string $placeholder
	 * @param object $db // The database object
	 * @return string
	 */
	public static function select($params) // ID Should not be array
	{
		// Blank defaults
		$name = $values = $current = $id = $placeholder = '';
		$class = $css = $disable = [];

		extract($params, EXTR_OVERWRITE);

	// Prepare arguments for predefined select
		if (is_array($name) && sizeof($name) == 1 && isset($db) && is_object($db)) { // Database Driven. Determine select name and table name
			$table = $name;
			$name = key($table);
			$table = reset($table);

			if (is_array($values) && sizeof($values) == 1) { // Determine key and value fields to fetch
				$val2 = $values;
				$val1 = key($val2);
				$val2 = reset($val2);
			}
			$values = array(); // Reset
			$data = $db->select($val1 . ',' . $val2)->table($table)->getAll();
			foreach ($data as $value) {
				$values[$value->$val1] = $value->$val2;
			}
		} else if (is_string($values)) { // Preset Select. Prepare data array.
			switch ($values) {
				case 'language':
					$lang_dirs = glob('languages/*', GLOB_ONLYDIR); // Get available language directory names
					$lang_defs = include('presets/language.php'); // Global language short codes : ISO 639-1 #####################################
					$disable = array(); // Blank disabled language array
					foreach ($lang_dirs as $dir) {
						$val[basename($dir)] = $lang_defs[basename($dir)];
						if (file_exists($dir . '/lock')) $disable[] = basename($dir);// Disable languages
					}
					break;

				case 'timezone':
					$zoneList = timezone_identifiers_list();
					foreach ($zoneList as $zone) {
						$diff = floor(self::offset('UTC', $zone)) / 60;
						$plus = $diff >= 0 ? "+" : "";
						$diff = ' [UTC ' . $plus . floor($diff / 60) . ':' . ($diff % 60) . ']';
						$val[$zone] = str_replace("_", " ", $zone) . $diff;
					}
					break;

				default: // Unknown or presets
					if (file_exists(DRT . 'core/lexis/' . $values . '.php')) {
						$val = include(DRT . 'core/lexis/' . $values . '.php');
					} else {
						$val = array();
					}
					break;
			}
			$values = $val;
		}

		if (is_array($current)) { // Its a multi select
			$multi = 'multiple';
			$name .= '[]'; // Array naming
		} else {
			$multi = '';
			$current = array($current);
		}

//		$id = empty($id) ? '' : ' id="' . implode(' ', $id) . '"'; // MULTIPLE IDS?
		$disw = (!is_array($disable)) ? 'disabled' : ''; // The whole select is disabled

		if (!empty($placeholder) || (is_array($class) && in_array('select2', $class))) { // Select 2 must have placeholder
			$s2d = self::elem('option', $placeholder, [self::inlineCss(['display' => 'none'])], false); // Select2 dummy option for placeholder
		} else {
			$css['width'] = isset($css['width']) ? $css['width'] : '100%'; // Only for select2 inside gridism
			$s2d = '<option></option>';
		}
		$class = empty($class) ? '' : implode(' ', $class);
		$style = self::inlineCss($css);

		// Lets build the final select code
		$select = $s2d;
		foreach ($values as $key => $value) {
			$sel = in_array($key, $current) ? 'selected' : '';
			$dis = (!empty($disable) && is_array($disable) && in_array($key, $disable)) ? 'disabled' : '';
			$select .= self::elem('option', $value, ['value' => $key, $sel => $sel, $dis => $dis], false);
		}
		$select = self::elem('select', $select, ['name' => $name, 'id' => $id, 'class' => $class, $style, $disw => $disw, $multi => $multi]);
		return $select;
	}

	public function table(array $headings = [], string $content = "")
	{
		$thead = "";
		if (!empty($headings)) {
			foreach ($headings as $head) {
				$thead .= $this->elem('th', $head, [], false);
			}
		}
		return $this->elem('table', $this->elem('thead', $this->elem('tr', $thead)) . $this->elem('tbody', $content));
	}

	public static function inlineCss(array $declarations = array())
	{
		if (!empty($declarations)) {
			$lines = array();
			foreach ($declarations as $property => $value) {
				$lines[] = $property . ": " . $value . ";";
			}
			return "style=\"" . (implode(' ', $lines)) . "\"";
		}
		return "";
	}

	public function incl(array $resource = array())
	{
		$code = '';
		$known = ['css', 'js'];
		if (!empty($resource)) {
			foreach ($resource as $type => $path) {
				if (!is_array($path)) $path = [$path];
				foreach ($path as $inc) {
					switch ($type) {
						case 'css':
							$code .= $this->elem('link', '', ['type' => 'text/css', 'rel' => 'stylesheet', 'href' => $inc]);
							break;

						case 'js':
							$code .= $this->elem('script', '', ['type' => 'text/javascript', 'src' => $inc], 0);
							break;

						default:
							# auto identify
							$ext = pathinfo(parse_url(trim($inc))['path'], PATHINFO_EXTENSION);
							if (in_array($ext, $known)) {
								$code .= $this->incl([$ext => $path]);
							}
							break;
					}
				}
			}
		}
		return $code;
	}

	public function moreInfo(string $info, string $align = '')
	{
		if ($align == 'l' || $align == '<') $align = 'left';
		if ($align == 'r' || $align == '>') $align = 'right';
		$args = ['class' => 'more-info fa fa-question-circle', 'area-hidden' => 'true', 'title' => 'More Info', 'data-info' => $info];
		if($align == 'left' || $align == 'right') $args['style'] = 'float: '.$align.';';
		return $this->elem('i', '', $args);
	}
		
	// Time Offset Handler
	// -----------------------------------------------------------------------
	/*
	Examples:
	// This will return 10800 (3 hours) ...
	$offset = get_timezone_offset('America/Los_Angeles','America/New_York');
	// or, if your server time is already set to 'America/New_York'...
	$offset = get_timezone_offset('America/Los_Angeles');
	// You can then take $offset and adjust your timestamp.
	$offset_time = time() + $offset;
	 */
	private static function offset($remote_tz, $origin_tz = null)
	{
		if ($origin_tz === null) {
			if (!is_string($origin_tz = date_default_timezone_get())) {
				return false; // A UTC timestamp was returned -- bail out!
			}
		}
		$origin_dtz = new \DateTimeZone($origin_tz);
		$remote_dtz = new \DateTimeZone($remote_tz);
		$origin_dt = new \DateTime("now", $origin_dtz);
		$remote_dt = new \DateTime("now", $remote_dtz);
		$offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
		return $offset;
	}
}