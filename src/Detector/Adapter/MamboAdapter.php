<?php
/**
 * @package    CMSScanner
 * @copyright  Copyright (C) 2014 CMS-Garden.org
 * @license    GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link       http://www.cms-garden.org
 */

namespace Cmsgarden\Cmsscanner\Detector\Adapter;

use Cmsgarden\Cmsscanner\Detector\System;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class MamboAdapter
 * @package Cmsgarden\Cmsscanner\Detector\Adapter
 *
 * @since   1.0.0
 */
class MamboAdapter implements AdapterInterface
{
    /**
     * Mambo has changed the way how the version number is stored multiple times, so we need this comprehensive array
     * @var array
     */
    private $version = array(
            "files" => array(
                "/version.php",
                "/includes/version.php"
            ),
            "regex_release" => "/\\\$?RELEASE'?\s*[=,]\s*'([\d.]+).*';/",
            "regex_release" => "/RELEASE'?\s*[=,]\s*'([\d.]+).*';?/",
            "regex_devlevel" => "/\\\$?DEV_LEVEL'?\s*[=,]\s*'([^']+)';/",
            "regex_devlevel" => "/\\\$?DEV_LEVEL'?\s*[=,]\s*'([^']+)';?/",
        );

    /**
     * Mambo has a file called configuration.php that can be used to search for working installations
     *
     * @param   Finder  $finder  finder instance to append the criteria
     *
     * @return  Finder
     */
    public function appendDetectionCriteria(Finder $finder)
    {
        $finder->name('configuration.php');

        return $finder;
    }

    /**
     * try to verify a search result and work around some well known false positives
     *
     * @param   SplFileInfo  $file  file to examine
     *
     * @return  bool|System
     */
    public function detectSystem(SplFileInfo $file)
    {
        if ($file->getFilename() != "configuration.php") {
            return false;
        }

	$found = false;
        foreach ($this->version['files'] as $versionFile) {
	    $versionFile = $file->getPath() . $versionFile;
	    //printf ("Check for Mambo version.php? %s\n", $versionFile);
	    if (! file_exists($versionFile) 
		|| ! is_readable($versionFile)) {
		continue;
	    }
	    if (stripos(file_get_contents($versionFile), "Mambo") !== false) {
		//printf ("Mambo found in %s\n", $versionFile);
		$found = true;
		break;
	    }
	}
	if (! $found) {
	    return false;
	}

        //if (stripos($file->getContents(), "JConfig") === false
        if (stripos($file->getContents(), "class JConfig") !== false
            && stripos($file->getContents(), 'mosConfig') === false) {
            return false;
        }

        // False positive "Akeeba Backup Installer"
        if (stripos($file->getContents(), "class ABIConfiguration") !== false) {
            return false;
        }

        // False positive mock file in unit test folder
        if (stripos($file->getContents(), "Mambo.UnitTest") !== false) {
            return false;
        }

        // False positive mock file in unit test folder
        if (stripos($file->getContents(), "Mambo\Framework\Test") !== false) {
            return false;
        }

        $path = new \SplFileInfo($file->getPath());

	if ($path === null) {
	    printf("OOPS: path is null\n");
	}

        // Return result if working
        return new System($this->getName(), $path);
    }

    /**
     * determine version of a Mambo installation within a specified path
     *
     * @param   \SplFileInfo  $path  directory where the system is installed
     *
     * @return  null|string
     */
    public function detectVersion(\SplFileInfo $path)
    {
        // Iterate through version files
        foreach ($this->version['files'] as $file) {
            $versionFile = $path->getRealPath() . $file;

            if (!file_exists($versionFile)) {
                continue;
            }

            if (!is_readable($versionFile)) {
                continue; // @codeCoverageIgnore
            }

	    //printf("checking mambo version: %s\n", $this->version['regex_release']);
            preg_match($this->version['regex_release'], file_get_contents($versionFile), $release);
            preg_match($this->version['regex_devlevel'], file_get_contents($versionFile), $devlevel);

            if (!count($release)) {
                continue;
            }

            if (!count($devlevel)) {
                return $release[1] . '.x';
            }

            return $release[1] . '.' . $devlevel[1];
        }

        return null;
    }

    /***
     * @return string
     */
    public function getName()
    {
        return 'Mambo';
    }
}
