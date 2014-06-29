<?php
namespace W4Y\Tests\Crawler;
use W4Y\Crawler\Filter;

class FilterTest extends \PHPUnit_Framework_TestCase
{
    /** @var Filter filter */
    private $filter;

    public function setUp()
    {
        $this->filter = new Filter();
    }

    public function testFilterMustContain()
    {
        $this->filter->reset();
        $this->filter->setFilter('foo foo', Filter::MUST_CONTAIN);

        $testString = 'This string contains foo foo.';
        $result = $this->filter->isValid($testString);
        $this->assertTrue($result);

        $this->filter->reset();
        $this->filter->setFilter('contains fb', Filter::MUST_CONTAIN);

        $testString = 'This string contains foo foo.';
        $result = $this->filter->isValid($testString);
        $this->assertFalse($result);
    }

    public function testFilterMustNotContain()
    {
        $this->filter->reset();
        $this->filter->setFilter('foo bar', Filter::MUST_NOT_CONTAIN);

        $testString = 'This string contains foo foo.';
        $result = $this->filter->isValid($testString);
        $this->assertTrue($result);

        $this->filter->reset();
        $this->filter->setFilter('string', Filter::MUST_NOT_CONTAIN);

        $testString = 'This string contains foo foo.';
        $result = $this->filter->isValid($testString);
        $this->assertFalse($result);
    }

    public function testFilterMustMatch()
    {
        $this->filter->reset();
        $this->filter->setFilter('#foo\s?foo#', Filter::MUST_MATCH);

        $testString = 'This string contains foofoo and ends with bar.';
        $result = $this->filter->isValid($testString);
        $this->assertTrue($result);

        $this->filter->reset();
        $this->filter->setFilter('#foo\s?foo#', Filter::MUST_MATCH);

        $testString = 'This string contains foo4foo and ends with bar.';
        $result = $this->filter->isValid($testString);
        $this->assertFalse($result);
    }

    public function testFilterMustNotMatch()
    {
        $this->filter->reset();
        $this->filter->setFilter('#anything#', Filter::MUST_NOT_MATCH);

        $testString = 'This string contains foo foo and ends with bar.';
        $result = $this->filter->isValid($testString);
        $this->assertTrue($result);

        $this->filter->reset();
        $this->filter->setFilter('#foo\s?foo#', Filter::MUST_NOT_MATCH);

        $testString = 'This string contains foo foo and ends with bar.';
        $result = $this->filter->isValid($testString);
        $this->assertFalse($result);
    }
}