<?php
echo "PHP動作確認: OK<br>";
echo "PHPバージョン: " . PHP_VERSION . "<br>";
echo "BASE_PATH: " . __DIR__ . "<br>";
echo "config/存在: " . (is_dir(__DIR__ . '/config') ? 'YES' : 'NO') . "<br>";
echo "config/書き込み: " . (is_writable(__DIR__ . '/config') ? 'YES' : 'NO') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "<br>";
echo "cURL: " . (extension_loaded('curl') ? 'YES' : 'NO') . "<br>";
echo "mbstring: " . (extension_loaded('mbstring') ? 'YES' : 'NO') . "<br>";
