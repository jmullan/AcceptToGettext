<?php
namespace tests;

class ScorerTest extends \PHPUnit_Framework_TestCase
{
    public function testPickLocale()
    {
        $scorer = new \AcceptToGettext\Scorer();
        $result = $scorer->pickLocale(array('de_DE'), array());
        $this->assertEquals($result, array ('de-de', 'UTF-8', 'de_DE'));

        $result = $scorer->pickLocale(array('de_DE', 'cs_CZ.UTF-8'), array());
        $this->assertEquals($result, array ('de-de', 'UTF-8', 'de_DE'));

        $result = $scorer->pickLocale(array('de_DE', 'cs_CZ.UTF-8'), array('HTTP_ACCEPT_LANGUAGE' => 'cs'));
        $this->assertEquals($result, array ('cs-cz', 'UTF-8', 'cs_CZ.UTF-8'));

        $result = $scorer->pickLocale(array('en'), array('HTTP_ACCEPT_LANGUAGE' => 'en'));
        $this->assertEquals($result, array ('en', 'UTF-8', 'en'));
    }
}
