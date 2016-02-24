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

function parse_gettext_mo()
{
    $result = array();
    foreach (mo_files() as $file) {
        $reader = Gettext\Translations::fromMoFile($file);
        $result[] = $reader;
    }
    return $result;
}

function parse_php_gettext()
{
    $result = array();
    foreach (mo_files() as $file) {
        $input = new FileReader($file);
        $reader = new gettext_reader($input);
        $reader->load_tables();
        $result[] = $reader;
    }
    return $result;
}

function parse_motranslator()
{
    $result = array();
    foreach (mo_files() as $file) {
        $reader = new MoTranslator\Translator($file);
        $result[] = $reader;
    }
    return $result;
}

function parse_moreader()
{
    $result = array();
    foreach (mo_files() as $file) {
        $reader = new \MoReader\Reader();
        $result[] = $reader->load($file);
    }
    return $result;
}

function parse_pgettext()
{
    $result = array();
    foreach (mo_files() as $file) {
        $result[] = \Pgettext\Mo::fromfile($file);
    }
    return $result;
}

for ($i = 0; $i < 10; $i++) {
    echo "\nTest round $i\n";
    measure('parse_gettext_mo');
    measure('parse_php_gettext');
    measure('parse_motranslator');
    measure('parse_moreader');
    measure('parse_pgettext');
}
