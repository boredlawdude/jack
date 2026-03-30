<?php
echo "<pre>";
echo "sys_get_temp_dir()   = " . sys_get_temp_dir() . "\n";
echo "Tempnam result       = " . tempnam(sys_get_temp_dir(), 'test_') . "\n";
echo "DIRECTORY_SEPARATOR  = " . DIRECTORY_SEPARATOR . "\n";
echo "is_writable(/tmp)    = " . (is_writable('/tmp') ? 'YES' : 'NO') . "\n";
echo "is_dir(/tmp)         = " . (is_dir('/tmp') ? 'YES' : 'NO') . "\n";
echo "</pre>";