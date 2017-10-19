<?php

require_once("src/icopyscape.php");
require_once("src/copyscape.php");


class CopyscapeTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {

    }

    public function tearDown()
    {

    }


    public function testCopyscape()
    {
        $settings = array(
            "copyscape_user_name"=>"bob",
            "copyscape_api_key" => "456"
        );
        $c = new \kdaviesnz\copyscape\Copyscape("http://example.com", $settings);
        echo $c;
    }

}
