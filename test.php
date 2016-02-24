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


function po_files()
{
    static $ret = null;
    if (is_null($ret)) {
        $ret = glob('../phpmyadmin/po/*.po');
    }
    return $ret;
}

function mo_files()
{
    static $ret = null;
    if (is_null($ret)) {
        $ret = glob('../phpmyadmin/locale/*/LC_MESSAGES/phpmyadmin.mo');
    }
    return $ret;
}

function parse_sepia()
{
    foreach (po_files() as $file) {
        $fileHandler = new Sepia\FileHandler($file);

        $poParser = new Sepia\PoParser($fileHandler);
        $entries  = $poParser->parse();
    }
}

function parse_gettext_po()
{
    foreach (po_files() as $file) {
        $translations = Gettext\Translations::fromPoFile($file);
    }
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

function parse_pofile()
{
    foreach (po_files() as $file) {
        $poFile = new Geekwright\Po\PoFile();
        $poFile->readPoFile($file);
    }
}

function parse_drupal()
{
    foreach (po_files() as $file) {
        $reader = new Drupal\Component\Gettext\PoStreamReader();
        $reader->setUri($file);
        $reader->open();
        while ($item = $reader->readItem()) {
        }
    }
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

measure('parse_gettext_mo');
measure('parse_php_gettext');
measure('parse_motranslator');
measure('parse_moreader');
measure('parse_gettext_mo');
measure('parse_php_gettext');
measure('parse_motranslator');
measure('parse_moreader');
//measure('parse_sepia');
//measure('parse_gettext_po');
//measure('parse_pofile');
//measure('parse_drupal');
