<?php

require_once(__DIR__ . "/Exceptions/Exceptions.inc.php");

//set_error_handler("warning_handler", E_WARNING | E_NOTICE);
//dns_get_record();
//restore_error_handler();

function warning_handler($errno, $errstr) {
	throw new Exception($errstr, $errno);
}
//dump($config);

