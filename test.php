#!/usr/bin/php
<?php

require "PHPSTLTemplate.php";

Compiler::$CacheDirectory = dirname(__FILE__).'/template-test-cache';
$t = new PHPSTLTemplate(dirname(__FILE__).'/test.xml');

print $t->render();

?>