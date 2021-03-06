<?php

// Copyright (c) 2002 Cunningham & Cunningham, Inc.
// Released under the terms of the GNU General Public License version 2 or later.

class PhpFit_Parse
{
    /**
     * @var string
     */
    public $leader;
    public $tag;
    public $body;
    public $end;
    public $trailer;

    /**
     * @var PhpFit_Parse
     */
    public $parts;
    public $more;

    /**
     * @var array
     */
    public static $tags = array ('table', 'tr', 'td');

    /**
    * Use PhpFit_Parse::create...
    */
    private function __construct()
    {
    }

    /**
     * @param string $text
     * @param array $tags
     * @param int $level
     * @param int $offset
     */
    public static function create($text, $tags = null, $level = 0, $offset = 0) 
    {
        $instance = new PhpFit_Parse();

        if ($tags == null) {
            $tags = PhpFit_Parse::$tags;
        }

        $startTag = stripos($text, '<' . $tags[$level]);
        $endTag = stripos($text, '>', $startTag) + 1;
        $startEnd = stripos($text, '</' . $tags[$level], $endTag);
        $endEnd = stripos($text, '>', $startEnd) + 1;
        $startMore = stripos($text, '<' . $tags[$level], $endEnd);

        if ($startTag === false || $endTag === false || $startEnd === false || $endEnd === false) {
            throw new PHPFit_Exception_Parse('Can\'t find tag: ' . $tags[$level], $offset);
        }

        $instance->leader = substr($text, 0, $startTag);
        $instance->tag = substr($text, $startTag, $endTag - $startTag);
        $instance->body = substr($text, $endTag, $startEnd - $endTag);
        $instance->end = substr($text, $startEnd, $endEnd - $startEnd);
        $instance->trailer = substr($text, $endEnd);

        // we are not at cell-level, so dig further down
        if (($level + 1) < count($tags)) {
            $instance->parts = PhpFit_Parse::create($instance->body, $tags, $level + 1, $offset + $endTag);
            $instance->body = null;
        }

        // if you have more of the same
        if ($startMore !== false) {
            $instance->more = PhpFit_Parse::create($instance->trailer, $tags, $level, $offset + $endEnd);
            $instance->trailer = null;
        }

        return $instance;
    }

    public static function createSimple($tag, $body = null, $parts = null, $more = null) 
    {
        $instance = new PhpFit_Parse();

        $instance->leader = "\n";
        $instance->tag = "<" . $tag . ">";
        $instance->body = $body;
        $instance->end = "</" . $tag . ">";
        $instance->trailer = "";
        $instance->parts = $parts;
        $instance->more = $more;

        return $instance;
    }

    /**
     * @return int
     */
    public function size() 
    {
        return ($this->more == null) ? 1 : $this->more->size() + 1;
    }

    /**
     * @return PhpFit_Parse
     */
    public function last() 
    {
        return ($this->more == null) ? $this : $this->more->last();
    }

    /**
     * @return PhpFit_Parse
     */
    public function leaf() 
    {
        return ($this->parts == null) ? $this : $this->parts->leaf();
    }

    /**
     * @param int $i table
     * @param int $j row
     * @param int $k column
     * @return PhpFit_Parse
     */
    public function at($i, $j = null, $k = null) 
    {
        if ($j !== null && $k !== null) // 3 params
            return $this->at($i, $j)->parts->at($k);
        else if ($j !== null) // 2 params
            return $this->at($i)->parts->at($j);
        else // 1 param
            return ($i == 0 || $this->more == null) ? $this : $this->more->at($i - 1);
    }

    /**
     * @return string
     */
    public function text() 
    {
        return PhpFit_Parse::htmlToText($this->body);
    }

    /**
     * @param string $s
     * @return string
     */
    public static function htmlToText($s) 
    {
        $s = PhpFit_Parse::normalizeLineBreaks($s);
        $s = PhpFit_Parse::removeNonBreakTags($s);
        $s = PhpFit_Parse::condenseWhitespace($s);
        $s = PhpFit_Parse::unescape($s);

        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    public static function unescape($s) 
    {
        $s = str_replace("<br />", "\n", $s);
        $s = PhpFit_Parse::unescapeEntities($s);
        $s = PhpFit_Parse::unescapeSmartQuotes($s);
        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function unescapeEntities($s) 
    {
        $s = str_replace('&lt;', '<', $s);
        $s = str_replace('&gt;', '>', $s);
        $s = str_replace('&nbsp;', ' ', $s);
        $s = str_replace('&quot;', '\"', $s);
        $s = str_replace('&amp;', '&', $s);
        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    public static function unescapeSmartQuotes($s) 
    {
        /* NOT SURE */
        $s = ereg_replace('<93>', '"', $s);
        $s = ereg_replace('<94>', '"', $s);
        $s = ereg_replace('<91>', "'", $s);
        $s = ereg_replace('<92>', "'", $s);

        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function normalizeLineBreaks($s) 
    {
        $s = preg_replace('|<\s*br\s*/?\s*>|s', '<br />', $s);
        $s = preg_replace('|<\s*/\s*p\s*>\s*<\s*p( .*?)?>|s', '<br />', $s);
        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    public static function condenseWhitespace($s) 
    {
        $nonBreakingSpace = chr(160);

        $s = preg_replace('|\s+|s', ' ', $s);
        $s = ereg_replace($nonBreakingSpace, ' ', $s);
        $s = ereg_replace('&nbsp;', ' ', $s);

        $s = trim($s, "\t\n\r\ ");
        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function removeNonBreakTags($s) 
    {
        $i = 0;
        $i = strpos($s, '<', $i);
        while ($i !== false) {
            $j = strpos($s, '>', $i +1);
            if ($j > 0) {
                if (substr($s, $i, $j +1 - $i) != '<br />') {
                    $s = substr($s, 0, $i) . substr($s, $j +1);
                } else {
                    $i++;
                }
            } else {
                break;
            }
            $i = strpos($s, '<', $i);

        }
        return $s;
    }

    /**
     * @param string $text
     */
    public function addToTag($text) 
    {
        $last = strlen($this->tag) - 1;
        $this->tag = substr($this->tag, 0, $last) . $text . '>';
    }

    /**
     * @param string $text
     */
    public function addToBody($text) 
    {
        $this->body = $this->body . $text;
    }

    /**
     * @return string
     */
    public function toString() 
    {
        $out = $this->leader;
        $out .= $this->tag;
        if ($this->parts != null) {
            $out .= $this->parts->toString();
        } else {
            $out .= $this->body;
        }
        $out .= $this->end;
        if ($this->more != null) {
            $out .= $this->more->toString();
        } else {
            $out .= $this->trailer;
        }
        return $out;
    }
}

class PHPFit_Exception_Parse extends Exception
{
    /**
    * @var int
    */
    protected $_offset = 0;

    /**
    * constructor
    *
    * @param string $msg
    * @param int $offset
    * @see Exception
    */
    public function __construct($msg, $offset) 
    {
        $this->_offset = $offset;
        $this->message = $msg;
        parent::__construct($this->message);
    }

    /**
    * @return int parser offset
    */
    public function getOffset() 
    {
        return $this->_offset;
    }

    /**
    * @return string of error message including offest
    */
    public function __toString() 
    {
        return $this->message . ' at ' . $this->_offset;
    }
}