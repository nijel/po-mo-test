<?php

require './lib.php';

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
        $reader = new \PhpMyAdmin\MoTranslator\Translator($file);
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

measure_main(array(
    'parse_gettext_mo',
    'parse_php_gettext',
    'parse_motranslator',
    'parse_moreader',
    'parse_pgettext',
));
