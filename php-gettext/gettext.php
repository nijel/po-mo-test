<?php
/*
   Copyright (c) 2003, 2009 Danilo Segan <danilo@kvota.net>.
   Copyright (c) 2005 Nico Kaiser <nico@siriux.net>

   This file is part of PHP-gettext.

   PHP-gettext is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   PHP-gettext is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PHP-gettext; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
 * Provides a simple gettext replacement that works independently from
 * the system's gettext abilities.
 * It can read MO files and use them for translating strings.
 * The files are passed to gettext_reader as a Stream (see streams.php)
 *
 * This version has the ability to cache all strings and translations to
 * speed up the string lookup.
 * While the cache is enabled by default, it can be switched off with the
 * second parameter in the constructor (e.g. whenusing very large MO files
 * that you don't want to keep in memory)
 */
class gettext_reader {
  //public:
   var $error = 0; // public variable that holds error code (0 if no error)

   //private:
  var $BYTEORDER = 'V';        // V: low endian, N: big endian
  var $STREAM = NULL;
  var $short_circuit = false;
  var $originals = NULL;      // offset of original table
  var $translations = NULL;    // offset of translation table
  var $pluralheader = NULL;    // cache header field for plural forms
  var $total = 0;          // total string count
  var $cache_translations = NULL;  // original -> translation mapping


  /* Methods */


  /**
   * Reads a 32bit Integer from the Stream
   *
   * @access private
   * @return Integer from the Stream
   */
  function readint() {
        $input=unpack($this->BYTEORDER, $this->STREAM->read(4));
        return array_shift($input);
    }

  function read($bytes) {
    return $this->STREAM->read($bytes);
  }

  /**
   * Reads an array of Integers from the Stream
   *
   * @param int count How many elements should be read
   * @return Array of Integers
   */
  function readintarray($count) {
        return unpack($this->BYTEORDER.$count, $this->STREAM->read(4 * $count));
  }

  /**
   * Constructor
   *
   * @param object Reader the StreamReader object
   */
  public function __construct($Reader) {
    // If there isn't a StreamReader, turn on short circuit mode.
    if (! $Reader || isset($Reader->error) ) {
      $this->short_circuit = true;
      return;
    }

    // Caching can be turned off
    $MAGIC1 = "\x95\x04\x12\xde";
    $MAGIC2 = "\xde\x12\x04\x95";

    $this->STREAM = $Reader;
    $magic = $this->read(4);
    if ($magic == $MAGIC1) {
      $this->BYTEORDER = 'N';
    } elseif ($magic == $MAGIC2) {
      $this->BYTEORDER = 'V';
    } else {
      $this->error = 1; // not MO file
      return false;
    }

    // FIXME: Do we care about revision? We should.
    $revision = $this->readint();

    $this->total = $this->readint();
    $this->originals = $this->readint();
    $this->translations = $this->readint();
  }

  /**
   * Loads the translation tables from the MO file into the cache
   * If caching is enabled, also loads all strings into a cache
   * to speed up translation lookups
   *
   * @access private
   */
  function load_tables() {
    if (is_array($this->cache_translations)) {
      return;
    }

    /* get original and translations tables */
      $this->STREAM->seekto($this->originals);
      $table_originals = $this->readintarray($this->total * 2);
      $this->STREAM->seekto($this->translations);
      $table_translations = $this->readintarray($this->total * 2);

      $this->cache_translations = array ();
      /* read all strings in the cache */
      for ($i = 0; $i < $this->total; $i++) {
        $this->STREAM->seekto($table_originals[$i * 2 + 2]);
        $original = $this->STREAM->read($table_originals[$i * 2 + 1]);
        $this->STREAM->seekto($table_translations[$i * 2 + 2]);
        $translation = $this->STREAM->read($table_translations[$i * 2 + 1]);
        $this->cache_translations[$original] = $translation;
      }
  }

  /**
   * Translates a string
   *
   * @access public
   * @param string string to be translated
   * @return string translated string (or original, if not found)
   */
  function translate($string) {
    if ($this->short_circuit)
      return $string;
    $this->load_tables();

      // Caching enabled, get translated string from cache
      if (array_key_exists($string, $this->cache_translations))
        return $this->cache_translations[$string];
      else
        return $string;
  }

  /**
   * Sanitize plural form expression for use in PHP eval call.
   *
   * @access private
   * @return string sanitized plural form expression
   */
  function sanitize_plural_expression($expr) {
    // Get rid of disallowed characters.
    $expr = preg_replace('@[^a-zA-Z0-9_:;\(\)\?\|\&=!<>+*/\%-]@', '', $expr);

    // Add parenthesis for tertiary '?' operator.
    $expr .= ';';
    $res = '';
    $p = 0;
    for ($i = 0; $i < strlen($expr); $i++) {
      $ch = $expr[$i];
      switch ($ch) {
      case '?':
        $res .= ' ? (';
        $p++;
        break;
      case ':':
        $res .= ') : (';
        break;
      case ';':
        $res .= str_repeat( ')', $p) . ';';
        $p = 0;
        break;
      default:
        $res .= $ch;
      }
    }
    return $res;
  }

  /**
   * Parse full PO header and extract only plural forms line.
   *
   * @access private
   * @return string verbatim plural form header field
   */
  function extract_plural_forms_header_from_po_header($header) {
    if (preg_match("/(^|\n)plural-forms: ([^\n]*)\n/i", $header, $regs))
      $expr = $regs[2];
    else
      $expr = "nplurals=2; plural=n == 1 ? 0 : 1;";
    return $expr;
  }

  /**
   * Get possible plural forms from MO header
   *
   * @access private
   * @return string plural form header
   */
  function get_plural_forms() {
    // lets assume message number 0 is header
    // this is true, right?
    $this->load_tables();

    // cache header field for plural forms
    if (! is_string($this->pluralheader)) {
        $header = $this->cache_translations[""];
      $expr = $this->extract_plural_forms_header_from_po_header($header);
      $this->pluralheader = $this->sanitize_plural_expression($expr);
    }
    return $this->pluralheader;
  }

  /**
   * Detects which plural form to take
   *
   * @access private
   * @param n count
   * @return int array index of the right plural form
   */
  function select_string($n) {
    $string = $this->get_plural_forms();
    $string = str_replace('nplurals',"\$total",$string);
    $string = str_replace("n",$n,$string);
    $string = str_replace('plural',"\$plural",$string);

    $total = 0;
    $plural = 0;

    eval("$string");
    if ($plural >= $total) $plural = $total - 1;
    return $plural;
  }

  /**
   * Plural version of gettext
   *
   * @access public
   * @param string single
   * @param string plural
   * @param string number
   * @return translated plural form
   */
  function ngettext($single, $plural, $number) {
    if ($this->short_circuit) {
      if ($number != 1)
        return $plural;
      else
        return $single;
    }

    // find out the appropriate form
    $select = $this->select_string($number);

    // this should contains all strings separated by NULLs
    $key = $single . chr(0) . $plural;


      if (! array_key_exists($key, $this->cache_translations)) {
        return ($number != 1) ? $plural : $single;
      } else {
        $result = $this->cache_translations[$key];
        $list = explode(chr(0), $result);
        return $list[$select];
      }
  }

  function pgettext($context, $msgid) {
    $key = $context . chr(4) . $msgid;
    $ret = $this->translate($key);
    if (strpos($ret, "\004") !== FALSE) {
      return $msgid;
    } else {
      return $ret;
    }
  }

  function npgettext($context, $singular, $plural, $number) {
    $key = $context . chr(4) . $singular;
    $ret = $this->ngettext($key, $plural, $number);
    if (strpos($ret, "\004") !== FALSE) {
      return $singular;
    } else {
      return $ret;
    }

  }
}

