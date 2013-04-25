<?php
class EmailFormat {
	const EOL = "\r\n";
	const LINE_WIDTH = 75;
	
	function &getInstance() {
		static $instance = array();

		if (!$instance) {
			$instance[0] =& new EmailFormat();
		}
		return $instance[0];
	}

	function html($text, $style = array()) {
		$text = EmailFormat::getBodyTag($text);
		$text = EmailFormat::absoluteUrls($text);
		$text = EmailFormat::replaceCssWithStyle($text, $style);
		$text = EmailFormat::linewrap($text, EmailFormat::LINE_WIDTH);
		return $text;
	}
	
	function text($text) {
		return EmailFormat::htmlToText($text);
	}

	function absoluteUrls($text) {
		$find = array(
			'@(<a[^>]+href="/)([^\"]*)("[^>]*>)@e',
			'@(<img[^>]+src="/)([^\"]*)("[^>]*>)@e',
		);
		if (defined('FULL_BASE_URL')) {
			$replace = '"$1' . FULL_BASE_URL . '$2$3";';
		} else {
			$replace = '"$1" . Router::url("$2", true) . "$3";';
		}
		return preg_replace($find, $replace, $text);
	}

	function linewrap($text, $width, $break = "\n", $cut = false) {
		$array = explode("\n", $text);
		$text = "";
		foreach($array as $key => $val) {
			$text .= wordwrap($val, $width, $break, $cut);
			$text .= "\n";
		}
		return $text;
	}
	
	function replaceCssWithStyle($text, $style = array()) {
		$tags = array('h1', 'h2', 'h3', 'h4', 'p', 'a', 'blockquote');
		$replace = array();
		foreach ($tags as $tag) {
			if (!empty($style[$tag])) {
				$replace['#(<' . $tag . ')([^>]*)(>)#'] = '$1 style="' . $style[$tag] . '"$2$3';
			}
		}
		return preg_replace(array_keys($replace), $replace, $text);
	}

	function htmlToText($text) {
		$eol = EmailFormat::EOL;
		$text = EmailFormat::getBodyTag($text);
		$text = EmailFormat::absoluteUrls($text);
		
		//Block-level HTML items
		$blocks = array('div','li','ul','td','tr','th','p','dd','h[\d]');
		//Uppercase HTML elements
		$uppers = array('h[\d]','dt','th');
		
		preg_match_all('/<a[\s+]href="([^\"]*)"/', $text, $matches);
	
		//Unique URLs
		$uniqueUrls = array_flip(array_flip($matches[1]));
		if (!empty($uniqueUrls)) {
			$urlIds = array_combine($uniqueUrls, range(1, count($uniqueUrls)));
		} else {
			$urlIds = array();
		}
		$urlCount = 0;
		
		$replace = array(
			'/([\{\}\$])/' => '\\$1',
			'@(<[/]{0,1}(' . implode('|',$blocks) . ')>)@ms' => '$1' . $eol,
			'/(\<img([^>]+)>)/' => '[IMAGE]',
			'/<a[\s+]href="([^\"]*)"[^>]*>http:(.*)<\/a>/' => '[ $1 ]',
			'/<a[\s+]href="([^\"]*)"[^>]*>(.*)<\/a>/e' => '"[" . $urlIds["$1"] . "] $2 "',	//
			'/<li>/' => '- ',			//Removes list items
			'@<\!\-(.*?)\->@ms' => '', //Removes comments
		);
		foreach ($uppers as $tag) {
			$replace['@(<' . $tag . '>(.*?)</'.$tag.'>)@ems'] = '$eol . strtoupper("$1");';
		}
		$text = preg_replace(array_keys($replace), $replace, $text);
		
		//Removes additional tags
		$text = strip_tags($text);
		$text = html_entity_decode($text,ENT_QUOTES);
		$text = EmailFormat::linewrap($text, EmailFormat::LINE_WIDTH, $eol);
		if (!empty($urlIds)) {
			$text .= $eol . $eol . 'References:' . $eol;
			foreach ($urlIds as $url => $id) {
				$text .= $id . ': ' . Router::url($url, true) . $eol;
			}
		}
		$text = preg_replace("/([$eol]{3,})/", $eol . $eol, $text);
		return stripslashes($text);
	}
	
	function getBodyTag($text) {
		if (preg_match('@<body>[\r\n]*(.*?)[\r\n]*</body>@ms', $text, $matches)) {
			$text = $matches[1];
		}
		return str_replace(array("\t","\n","\r"), '', $text);
	}

}
