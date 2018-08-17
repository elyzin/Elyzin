<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Model : User
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 * 
 * @todo Namespace
 * @todo Interface, Form elements, Markdown Parser
 */

class HTGen
{
	protected $void_tags = ['area', 'base', 'br', 'col', 'hr', 'img', 'input', 'link', 'meta', 'param', 'command', 'keygen', 'source']; // HTML5 Compliant Void Tags

	public function __construct()
	{
	}

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
	public function elem($tag = 'br', $content = '', $arg = array(), $multiline = true, $xhtml = false)
	{
		$decl = "";
		if (!empty($arg)) { // && !in_array($tag, ['br','hr']))
			$decl = array();
			foreach ($arg as $attrib => $value) {
				if (is_array($value)) $value = implode(', ', $value);
				if (is_numeric($attrib)) {
					$attrib = $value;
					$value = '';
				}
				$value = (!$value) ? '' : '="' . $value . '"';
				$decl[] = $attrib . $value;
			}
			$decl = empty($decl) ? '' : ' ' . implode(' ', $decl);
		}
		if (in_array($tag, $this->void_tags)) {
			$multiline = false; // Force oneline for void tags
			$startclose = $content = '';
			$endopen = $xhtml ? ' /' : ''; // XHTML compatibility, false by default
		} else {
			$startclose = '>';
			$endopen = '</' . $tag;
		}

		$eol = $multiline ? PHP_EOL : '';
		return '
<' . $tag . $decl . $startclose . $content . $eol . $endopen . '>';
	}
}