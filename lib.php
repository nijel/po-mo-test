<?php
require './vendor/autoload.php';
require './php-gettext/gettext.php';
require './php-gettext/streams.php';

function measure($func)
{
    $memory = memory_get_usage();
    $start = microtime(true);

    $data = call_user_func($func);

    $end = microtime(true);
    $diff = $end - $start;
    $memory = memory_get_usage() - $memory;
    echo "Ran $func in $diff seconds. takes $memory bytes\n";
}


function mo_files()
{
    static $ret = null;
    if (is_null($ret)) {
        $ret = glob('./locale/*/LC_MESSAGES/phpmyadmin.mo');
    }
    return $ret;
}

function measure_main($measurements)
{
    for ($i = 0; $i < 10; $i++) {
        echo "\nTest round $i\n";
        foreach ($measurements as $measure) {
            measure($measure);
        }
    }
}
