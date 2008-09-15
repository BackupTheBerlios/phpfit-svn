<?php

// Copyright (c) 2002 Cunningham & Cunningham, Inc.
// Released under the terms of the GNU General Public License version 2 or later.

require_once 'PHPUnit/Framework.php';
require_once 'ParseTest.php';

class AllTests
{
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('PhpFit Framework');
        
        $suite->addTestSuite('ParseTest');
        
        return $suite;        
    }  
}
