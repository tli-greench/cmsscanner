<?php
/**
 * @package    CMSScanner
 * @copyright  Copyright (C) 2014 CMS-Garden.org
 * @license    GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link       http://www.cms-garden.org
 */

namespace Cmsgarden\Cmsscanner\Tests\Adapters;

use Cmsgarden\Cmsscanner\Detector\Adapter\CM4allSitesAdapter;
use Symfony\Component\Finder\Finder;

/**
 * Class CM4allSitesAdapterTest
 * @package Cmsgarden\Cmsscanner\Tests\Adapters
 *
 * @since   1.0.0
 */
class CM4allSitesAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CM4allSitesAdapter
     */
    public $object;

    public function setUp()
    {
        $this->object = new CM4allSitesAdapter();
    }

    public function testCorrectNameIsReturned()
    {
        $this->assertEquals('CM4all Sites', $this->object->getName());
    }

    public function testSystemsAreDetected()
    {
        $finder = new Finder();
        $finder->files()->in(CMSSCANNER_MOCKFILES_PATH)
            ->name('dummy.php')
            ->name('index.php');

        $finder = $this->object->appendDetectionCriteria($finder);

        $results = array();
        $falseCount = 0;

        foreach ($finder as $file) {
            $system = $this->object->detectSystem($file);

            if ($system == false) {
                $falseCount++;
                continue;
            }

            $system->version = $this->object->detectVersion($system->getPath());

            // Append successful result to array
            $results[$system->version] = $system;
        }

        $this->assertCount(1, $results);
        $this->assertEquals(3, $falseCount);
        $this->assertArrayHasKey('2.3', $results);
        $this->assertInstanceOf('Cmsgarden\Cmsscanner\Detector\System', current($results));
    }
}
