<?php
App::uses('Router', 'Lib');

class EmailFormat {
	const EOL = "\r\n";
	const LINE_WIDTH = 75;							//How many characters are on a line before auto line break
	const PHP_TAG_REG = '@<\?php(.+?)\?>@is';		//Matches PHP tags inside a string

/**
 * Formats a string of HTML for use in email
 *
 * @param string $text The HTML string of your email body
 * @param Array $style An optional array of CSS style items
 *
 * @return string The HTML string formatted for email
 **/	 
	public static function html($text, $style = array()) {
		//Makes sure to only use text in the BODY tag
		$text = self::getBodyTag($text);
		$text = self::setAbsoluteUrls($text);
		$text = self::replaceCssWithStyle($text, $style);
		//$text = self::linewrap($text, self::LINE_WIDTH);
		return $text;
	}
	
/**
 * Formats any HTML elements in the string in the text-only friendly format
 *
 * @param string The body of your email, including any HTML elements needing converted
 *
 * @return string The text string with HTML elements parsed
 **/
	public static function text($text) {
		return self::htmlToText($text);
	}

/**
 * Ensures all URLs in the string include are absolute
 *
 * @param string The body of your email
 *
 * @return string The text with URLs parsed into absolute URLs
 **/
	public static  function setAbsoluteUrls($text) {
		$find = array(
			'@(<a[^>]+href=")(/)([^\"]*)("[^>]*>)@e',
			'@(<img[^>]+src=")(/)([^\"]*)("[^>]*>)@e',
		);
		$replace = sprintf('"$1%s/$3$4";', Router::fullBaseUrl());
		return  preg_replace($find, $replace, $text);
	}

/**
 * Inserts page breaks to a string to make sure it never exceeds a set amount of characters wide
 *
 * @param string $text The message text
 * @param int $width The amount of characters per line
 * @param string $break The line break to use
 * @param bool $cut If true, it will cut words that extend past the width edge
 *
 * @return string The formatted string
 **/
	public static function linewrap($text, $width, $break = "\n", $cut = false) {
		$lines = explode("\n", $text);
		$text = "";
		foreach($lines as $line) {
			$text .= wordwrap($line, $width, $break, $cut);
			$text .= "\n";
		}
		return $text;
	}

/**
 * Uses an array of CSS style elements and replaces them with inline elements
 *
 * @param string $text The message text
 * @param Array $style An array of CSS style elements, using the tag as the key
 * 		example: array('div' => 'border:1px solid red; font-weight: bold;');
 *
 * @return string The formatted string
 **/
	public static function replaceCssWithStyle($text, $style = array()) {
		$tags = array('h1', 'h2', 'h3', 'h4', 'p', 'a', 'blockquote');
		$replace = array();
		foreach ($tags as $tag) {
			if (!empty($style[$tag])) {
				$replace['#(<' . $tag . ')([^>]*)(>)#'] = '$1 style="' . $style[$tag] . '"$2$3';
			}
		}
		return preg_replace(array_keys($replace), $replace, $text);
	}

	//Parses an HTML string for display in a text-only format
	public static function htmlToText($text) {
		$text = self::getBodyTag($text);
		$text = self::setAbsoluteUrls($text);
		
		//Block-level HTML items
		$blocks = array('div','li','ul','td','tr','th','p','dd','h[\d]','br','hr');
		//Uppercase HTML elements
		$uppers = array('h[\d]','dt','th');
		
		$urlIds = self::getUniqueUrls($text);
		$preservedPhp = self::getPreservedPhp($text);
		
		$replace = array(
			'/([\{\}\$])/' => '\\$1',
			'@(<[/]{0,1}(' . implode('|',$blocks) . ')[/]{0,1}>)@ms' => '$1' . self::EOL,
			'/(\<img([^>]+)>)/' => '[IMAGE]',
			'/<a[\s+]href="([^\"]*)"[^>]*>http:(.*)<\/a>/' => '[ $1 ]',
			'/<a[\s+]href="([^\"]*)"[^>]*>(.*)<\/a>/e' => '"[" . $urlIds["$1"] . "] $2 "',	//
			'/<li>/' => '- ',			//Removes list items
			'@<\!\-(.*?)\->@ms' => '', //Removes comments
		);
		foreach ($uppers as $tag) {
			$replace['@(<' . $tag . '>(.*?)</'.$tag.'>)@ems'] = 'self::EOL . strtoupper("$1");';
		}
		$text = preg_replace(array_keys($replace), $replace, $text);
		
		//Removes additional tags
		$text = strip_tags($text);
		$text = html_entity_decode($text,ENT_QUOTES);
		$text = self::linewrap($text, self::LINE_WIDTH, self::EOL);
		$text = self::replaceUrlIds($text, $urlIds);
		
		//Removes extra end of line characters
		$text = preg_replace("/([" . self::EOL . "]{3,})/", self::EOL . self::EOL, $text);
		
		$text = self::setPreservedPhp($text, $preservedPhp);
		
		return trim(stripslashes($text));
	}
	
	//Returns all text within the body tag
	public static function getBodyTag($text) {
		if (preg_match('@<body>[\r\n]*(.*?)[\r\n]*</body>@ms', $text, $matches)) {
			$text = $matches[1];
		}
		return $text;
		//return str_replace(array("\t","\n","\r"), '', $text);
	}
	
/**
 * Removes PHP tags to prevent them being affected by adding return line characters
 *
 **/
	private static function getPreservedPhp(&$text) {
		$replace = array();
		if (preg_match_all(self::PHP_TAG_REG, $text, $matches)) {
			foreach ($matches[0] as $k => $match) {
				$rep = '###PRESERVE' . $k . '###';
				$replace[$match] = $rep;
			}
			$text = str_replace(array_keys($replace), $replace, $text);
		}
		return $replace;	
	}
	
/**
 * Takes an array of PHP tags, created with getPreservedPhp and re-inserts them back into the text
 *
 **/
	private static function setPreservedPhp($text, $preserved) {
		return str_replace($preserved, array_keys($preserved), $text);
	}
	
	private static function getUniqueUrls($text) {
		preg_match_all('/<a[\s+]href="([^\"]*)"/', $text, $matches);
		//Unique URLs
		$uniqueUrls = array_flip(array_flip($matches[1]));
		if (!empty($uniqueUrls)) {
			$urlIds = array_combine($uniqueUrls, range(1, count($uniqueUrls)));
		} else {
			$urlIds = array();
		}
		return $urlIds;
	}
	
	private static function replaceUrlIds($text, $urlIds) {
		if (!empty($urlIds)) {
			$text .= self::EOL . self::EOL . 'References:' . self::EOL;
			foreach ($urlIds as $url => $id) {
				$text .= $id . ': ' . self::url($url) . self::EOL;
			}
		}
		return $text;
	}
	
	public static function url($url) {
		return Router::url(self::removeUrlBase($url), true);
	}
	
	private static function removeUrlBase($url) {
		if (is_array($url)) {
			$url['base'] = false;
		} else {
			//If webroot is more than "/", remove it from the beginning
			if ($webroot = Router::fullBaseUrl()) {
				if (strpos($url, $webroot) === 0) {
					$url = substr($url, strlen($webroot));
				}
			}
		}
		return $url;

	}
}