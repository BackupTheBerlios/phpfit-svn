<?php

// Copyright (c) 2002 Cunningham & Cunningham, Inc.
// Released under the terms of the GNU General Public License version 2 or later.

require_once 'PHPUnit/Framework.php';
require_once 'PHPFit/Parse.php';

class ParseTest extends PHPUnit_Framework_TestCase
{
    public function testParsing() 
    {
        $p = PHPFit_Parse::create('leader<Table foo=2>body</table>trailer', array ('table'));
        $this->assertEquals('leader', $p->leader);
        $this->assertEquals('<Table foo=2>', $p->tag);
        $this->assertEquals('body', $p->body);
        $this->assertEquals('trailer', $p->trailer);
    }

    /**
     * ->parts : the columns of a table or the rows of a column.
     */
    public function testRecursing() 
    {
        $p = PHPFit_Parse::create('leader	<table><TR><Td>body</tD></TR></table>trailer');
        $this->assertEquals(null, $p->body);
        $this->assertEquals(null, $p->parts->body);
        $this->assertEquals("body", $p->parts->parts->body);
    }

    /**
     * ->more : the next column, the next row or the next table.
     */
    public function testIterating() 
    {
        $p = PHPFit_Parse::create('leader	<table><tr><td>one</td><td>two</td><td>three</td></tr></table>trailer');
        $this->assertEquals('one', $p->parts->parts->body);
        $this->assertEquals('two', $p->parts->parts->more->body);
        $this->assertEquals('three', $p->parts->parts->more->more->body);
    }

    public function testRegenerateContentFromParseTree() 
    {
        $filename = 'tests/html/arithmetic.html';
        $cont = file_get_contents($filename, true);
        if ($cont === false) {
            $this->fail("Can't read file " . $filename);
        }
        $p = PHPFit_Parse::create($cont);
        $this->assertEquals($cont, $p->toString());
    }

    public function testIndexing() 
    {
        $p = PHPFit_Parse::create('leader<table><tr><td>one</td><td>two</td><td>three</td></tr>' .
                                    '<tr><td>four</td></tr></table>trailer');
        $this->assertEquals("one", $p->at(0, 0, 0)->body);
        $this->assertEquals("two", $p->at(0, 0, 1)->body);
        $this->assertEquals("three", $p->at(0, 0, 2)->body);
        $this->assertEquals("three", $p->at(0, 0, 3)->body);
        $this->assertEquals("three", $p->at(0, 0, 4)->body);
        $this->assertEquals("four", $p->at(0, 1, 0)->body);
        $this->assertEquals("four", $p->at(0, 1, 1)->body);
        $this->assertEquals("four", $p->at(0, 2, 0)->body);
        $this->assertEquals(1, $p->size());
        $this->assertEquals(2, $p->parts->size());
        $this->assertEquals(3, $p->parts->parts->size());
        $this->assertEquals("one", $p->leaf()->body);
        $this->assertEquals("four", $p->parts->last()->leaf()->body);
    }

    public function testParseException() 
    {
        try {
            $p = PHPFit_Parse::create('leader<table><tr><th>one</th><th>two</th><th>three</th></tr>' .
                                        '<tr><td>four</td></tr></table>trailer');
            $this->fail("exptected exception not thrown");
        } catch (PHPFit_Exception_Parse $e) {
            $this->assertEquals(17, $e->getOffset());
            $this->assertEquals("Can't find tag: td", $e->getMessage());
        }
    }

    public function testText() 
    {
        $tags = array ('td');
        $p = PHPFit_Parse::create('<td>a&lt;b</td>', $tags);
        $this->assertEquals('a&lt;b', $p->body);
        $this->assertEquals('a<b', $p->text());
        $p = PHPFit_Parse::create("<td>\ta&gt;b&nbsp;&amp;&nbsp;b>c &&&lt;</td>", $tags);
        $this->assertEquals('a>b & b>c &&<', $p->text());
        $p = PHPFit_Parse::create("<td>\ta&gt;b&nbsp;&amp;&nbsp;b>c &&lt;</td>", $tags);
        $this->assertEquals('a>b & b>c &<', $p->text());
        $p = PHPFit_Parse::create('<TD><P><FONT FACE="Arial" SIZE=2>GroupTestFixture</FONT></TD>', $tags);
        $this->assertEquals("GroupTestFixture", $p->text());

        $this->assertEquals("", PHPFit_Parse::htmlToText("&nbsp;"));
        $this->assertEquals("a b", PHPFit_Parse::htmlToText("a <tag /> b"));
        $this->assertEquals("a", PHPFit_Parse::htmlToText("a &nbsp;"));
        $this->assertEquals("&nbsp;", PHPFit_Parse::htmlToText("&amp;nbsp;"));
        $this->assertEquals("1     2", PHPFit_Parse::htmlToText("1 &nbsp; &nbsp; 2"));
        $this->assertEquals("a", PHPFit_Parse::htmlToText("  <tag />a"));
        $this->assertEquals("a\nb", PHPFit_Parse::htmlToText("a<br />b"));
        $this->assertEquals("ab", PHPFit_Parse::htmlToText("<font size=+1>a</font>b"));
        $this->assertEquals("ab", PHPFit_Parse::htmlToText("a<font size=+1>b</font>"));
        $this->assertEquals("a<b", PHPFit_Parse::htmlToText("a<b"));

        $this->assertEquals("ab", PHPFit_Parse::htmlToText("<font size=+1>a</font>b"));
        $this->assertEquals("ab", PHPFit_Parse::htmlToText("a<font size=+1>b</font>"));
        $this->assertEquals("a<b", PHPFit_Parse::htmlToText("a<b"));

        $this->assertEquals("a\nb\nc\nd", PHPFit_Parse::htmlToText("a<br>b<br/>c<  br   /   >d"));
        $this->assertEquals("a\nb\nc", PHPFit_Parse::htmlToText("a<br>b<br />c"));
        $this->assertEquals("a\nb", PHPFit_Parse::htmlToText("a</p><p>b"));
        $this->assertEquals("a\nb", PHPFit_Parse::htmlToText("a< / p >   <   p  >b"));
    }

    public function testUnescape() 
    {
        $this->assertEquals("a<b", PHPFit_Parse::unescape("a&lt;b"));
        $this->assertEquals("a>b & b>c &&", PHPFit_Parse::unescape("a&gt;b&nbsp;&amp;&nbsp;b>c &&"));
        $this->assertEquals("&amp;&amp;", PHPFit_Parse::unescape("&amp;amp;&amp;amp;"));
        $this->assertEquals("a>b & b>c &&", PHPFit_Parse::unescape("a&gt;b&nbsp;&amp;&nbsp;b>c &&"));
        $this->assertEquals("\"\"'", PHPFit_Parse::unescape("<93><94><92>"));
    }

    public function testWhitespaceIsCondensed() 
    {
        $this->assertEquals("a b", PHPFit_Parse::condenseWhitespace(" a  b  "));
        $this->assertEquals("a b", PHPFit_Parse::condenseWhitespace(" a  \n\tb  "));
        $this->assertEquals("", PHPFit_Parse::condenseWhitespace(" "));
        $this->assertEquals("", PHPFit_Parse::condenseWhitespace("  "));
        $this->assertEquals("", PHPFit_Parse::condenseWhitespace("   "));
        $this->assertEquals("", PHPFit_Parse::condenseWhitespace(chr(160)));
    }

    public function testAddToTag() 
    {
        $p = PHPFit_Parse::create('leader<Table foo=2>body</table>trailer', array ('table'));
        $p->addToTag(" bgcolor=\"#cfffcf\"");
        $this->assertEquals("<Table foo=2 bgcolor=\"#cfffcf\">", $p->tag);
    }

    public function testFractBody() 
    {
        $p = PHPFit_Parse::create('leader<Table foo=2>0.5</table>trailer', array ('table'));
        $this->assertEquals('leader', $p->leader);
        $this->assertEquals('<Table foo=2>', $p->tag);
        $this->assertEquals('0.5', $p->text());
        $this->assertEquals('trailer', $p->trailer);
    }
}