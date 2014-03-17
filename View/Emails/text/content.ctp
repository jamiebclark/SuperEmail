<?php
if (!empty($altContent)) {
	$content = $altContent;
}
echo $this->Email->display($content, 'text', array('eval' => false, 'shrinkUrls' => false));
