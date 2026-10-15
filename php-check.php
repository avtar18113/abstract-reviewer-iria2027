<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'SAPI: ' . PHP_SAPI . PHP_EOL;
echo 'mysqli: ' . (extension_loaded('mysqli') ? 'enabled' : 'missing') . PHP_EOL;
echo 'session: ' . (extension_loaded('session') ? 'enabled' : 'missing') . PHP_EOL;
echo 'Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . PHP_EOL;
echo 'Current File: ' . __FILE__ . PHP_EOL;
