<?php
namespace W4Y\Tests\Crawler;

use W4Y\Crawler\Harvester;

class HarvesterTest extends \PHPUnit_Framework_TestCase
{
    /** @var Harvester $harvester */
    private $harvester;

    private $baseDir;

    public function setUp()
    {
        $this->harvester = new Harvester();
        $this->baseDir = __DIR__ . '/Assets';
    }

    private function getTestData()
    {
        return file_get_contents($this->baseDir . '/test.html');
    }

    public function testCanHarvestMultipleRulesNoCallback()
    {
        $this->harvester->setHarvestRule('Rule1', '.link');
        $this->harvester->setHarvestRule('Rule2', '.unmatching Rule');
        $this->harvester->setRenderType(Harvester::HARVEST_AS_ARRAY);

        $custom = 'MyData Unique Data';
        $html = $this->getTestData();

        $this->harvester->harvest(
            'Key',
            $html,
            $custom
        );

        $data = $this->harvester->fetchData();
        $data = current($data);

        $this->assertCount(3, $data['Rule1']);

        $this->assertArrayHasKey('Rule2', $data);
        $this->assertCount(0, $data['Rule2']);

        $this->assertArrayHasKey('_custom', $data);
        $this->assertEquals($custom, current($data['_custom']));
    }

    public function testCanHarvestMultipleRulesCallback()
    {
        $this->harvester->setHarvestRule('Rule1', '.link');
        $this->harvester->setHarvestRule('Rule2', '.unmatching Rule');
        $this->harvester->setRenderType(Harvester::HARVEST_AS_ARRAY);

        $custom = 'MyData Unique Data';
        $html = $this->getTestData();

        $this->harvester->harvest(
            'Key',
            $html,
            $custom
        );

        // Harvested data will be saved to this variable.
        $harvestData = array();

        // Callback to populate harvestedData.
        $callback = function ($data) use (&$harvestData) {
            $harvestData[] = $data;
        };

        // Harvest data
        $data = $this->harvester->fetchData($callback);

        // When callback is used no data is returned.
        $this->assertNull($data);

        // Get data
        $data = current($harvestData);

        $this->assertCount(3, $data['Rule1']);

        $this->assertArrayHasKey('Rule2', $data);
        $this->assertCount(0, $data['Rule2']);

        $this->assertArrayHasKey('_custom', $data);
        $this->assertEquals($custom, current($data['_custom']));
    }
}