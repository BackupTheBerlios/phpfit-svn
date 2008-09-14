<?php

// Copyright (c) 2002 Cunningham & Cunningham, Inc.
// Released under the terms of the GNU General Public License version 2 or later.

require_once 'PHPUnit/Framework.php';
require_once 'PHPFIT/Parse.php';

class ParseTest extends PHPUnit_Framework_TestCase
{
    public function testParsing() 
    {
        $p = PHPFIT_Parse :: create('leader<Table foo=2>body</table>trailer', array ('table'));
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
        $p = PHPFIT_Parse :: create('leader	<table><TR><Td>body</tD></TR></table>trailer');
        $this->assertEquals(null, $p->body);
        $this->assertEquals(null, $p->parts->body);
        $this->assertEquals("body", $p->parts->parts->body);
    }

    /**
     * ->more : the next column, the next row or the next table.
     */
    public function testIterating() 
    {
        $p = PHPFIT_Parse :: create('leader	<table><tr><td>one</td><td>two</td><td>three</td></tr></table>trailer');
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
        $p = PHPFIT_Parse :: create($cont);
        $this->assertEquals($cont, $p->toString());
    }

    public function testIndexing() 
    {
        $p = PHPFIT_Parse :: create('leader<table><tr><td>one</td><td>two</td><td>three</td></tr>' .
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
            $p = PHPFIT_Parse :: create('leader<table><tr><th>one</th><th>two</th><th>three</th></tr>' .
                                        '<tr><td>four</td></tr></table>trailer');
            $this->fail("exptected exception not thrown");
        } catch (PHPFIT_Exception_Parse $e) {
            $this->assertEquals(17, $e->getOffset());
            $this->assertEquals("Can't find tag: td", $e->getMessage());
        }
    }

    public function testText() 
    {
        $tags = array ('td');
        $p = PHPFIT_Parse :: create('<td>a&lt;b</td>', $tags);
        $this->assertEquals('a&lt;b', $p->body);
        $this->assertEquals('a<b', $p->text());
        $p = PHPFIT_Parse :: create("<td>\ta&gt;b&nbsp;&amp;&nbsp;b>c &&&lt;</td>", $tags);
        $this->assertEquals('a>b & b>c &&<', $p->text());
        $p = PHPFIT_Parse :: create("<td>\ta&gt;b&nbsp;&amp;&nbsp;b>c &&lt;</td>", $tags);
        $this->assertEquals('a>b & b>c &<', $p->text());
        $p = PHPFIT_Parse :: create('<TD><P><FONT FACE="Arial" SIZE=2>GroupTestFixture</FONT></TD>', $tags);
        $this->assertEquals("GroupTestFixture", $p->text());

        $this->assertEquals("", PHPFIT_Parse :: htmlToText("&nbsp;"));
        $this->assertEquals("a b", PHPFIT_Parse :: htmlToText("a <tag /> b"));
        $this->assertEquals("a", PHPFIT_Parse :: htmlToText("a &nbsp;"));
        $this->assertEquals("&nbsp;", PHPFIT_Parse :: htmlToText("&amp;nbsp;"));
        $this->assertEquals("1     2", PHPFIT_Parse :: htmlToText("1 &nbsp; &nbsp; 2"));
        $this->assertEquals("a", PHPFIT_Parse :: htmlToText("  <tag />a"));
        $this->assertEquals("a\nb", PHPFIT_Parse :: htmlToText("a<br />b"));
        $this->assertEquals("ab", PHPFIT_Parse :: htmlToText("<font size=+1>a</font>b"));
        $this->assertEquals("ab", PHPFIT_Parse :: htmlToText("a<font size=+1>b</font>"));
        $this->assertEquals("a<b", PHPFIT_Parse :: htmlToText("a<b"));

        $this->assertEquals("ab", PHPFIT_Parse :: htmlToText("<font size=+1>a</font>b"));
        $this->assertEquals("ab", PHPFIT_Parse :: htmlToText("a<font size=+1>b</font>"));
        $this->assertEquals("a<b", PHPFIT_Parse :: htmlToText("a<b"));

        $this->assertEquals("a\nb\nc\nd", PHPFIT_Parse :: htmlToText("a<br>b<br/>c<  br   /   >d"));
        $this->assertEquals("a\nb\nc", PHPFIT_Parse :: htmlToText("a<br>b<br />c"));
        $this->assertEquals("a\nb", PHPFIT_Parse :: htmlToText("a</p><p>b"));
        $this->assertEquals("a\nb", PHPFIT_Parse :: htmlToText("a< / p >   <   p  >b"));
    }

    public function testUnescape() 
    {
        $this->assertEquals("a<b", PHPFIT_Parse :: unescape("a&lt;b"));
        $this->assertEquals("a>b & b>c &&", PHPFIT_Parse :: unescape("a&gt;b&nbsp;&amp;&nbsp;b>c &&"));
        $this->assertEquals("&amp;&amp;", PHPFIT_Parse :: unescape("&amp;amp;&amp;amp;"));
        $this->assertEquals("a>b & b>c &&", PHPFIT_Parse :: unescape("a&gt;b&nbsp;&amp;&nbsp;b>c &&"));
        $this->assertEquals("\"\"'", PHPFIT_Parse :: unescape("<93><94><92>"));
    }

    public function testWhitespaceIsCondensed() 
    {
        $this->assertEquals("a b", PHPFIT_Parse :: condenseWhitespace(" a  b  "));
        $this->assertEquals("a b", PHPFIT_Parse :: condenseWhitespace(" a  \n\tb  "));
        $this->assertEquals("", PHPFIT_Parse :: condenseWhitespace(" "));
        $this->assertEquals("", PHPFIT_Parse :: condenseWhitespace("  "));
        $this->assertEquals("", PHPFIT_Parse :: condenseWhitespace("   "));
        $this->assertEquals("", PHPFIT_Parse :: condenseWhitespace(chr(160)));
    }

    public function testAddToTag() 
    {
        $p = PHPFIT_Parse :: create('leader<Table foo=2>body</table>trailer', array ('table'));
        $p->addToTag(" bgcolor=\"#cfffcf\"");
        $this->assertEquals("<Table foo=2 bgcolor=\"#cfffcf\">", $p->tag);
    }

    public function testFractBody() 
    {
        $p = PHPFIT_Parse :: create('leader<Table foo=2>0.5</table>trailer', array ('table'));
        $this->assertEquals('leader', $p->leader);
        $this->assertEquals('<Table foo=2>', $p->tag);
        $this->assertEquals('0.5', $p->text());
        $this->assertEquals('trailer', $p->trailer);
    }
}