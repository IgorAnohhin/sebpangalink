<?php
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
JHTML::_('behavior.modal');
echo "<h3>" . $this->paymentResponse . "</h3>";
if ($this->message) {
    echo "<fieldset>";
    echo $this->message;
    echo "</fieldset>";
}
/*foreach ($this->macFields as $f => $v) {	if (substr ($f, 0, 3) == 'VK_') {		echo $f . ':' . $v . '<br/>';	}}*/