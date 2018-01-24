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
 * Class CM4allSitesAdapter
 * @package Cmsgarden\Cmsscanner\Detector\Adapter
 *
 * @since   1.0.0
 * @author Thomas Linder <thomas.linder@green.ch>
 */
class CM4allSitesAdapter implements AdapterInterface
{

    /**
     * Version detection information for CM4allSitesAdapter
     * Actually, version will be Unknown but this is how we detect CM4all Sites for now
     * @var array
     */
    protected $versions = array(
        array(
            'indexname' => '/index.php',
            'cm4allincludename' => '/.cm4all/include/base.php',
            'regexp' => '"/\.cm4all/include/base\.php"'
        ),
    );

    /**
     * Just use index.php reference to .cm4all require'd file to detect
     *
     * @param   Finder  $finder  finder instance to append the criteria
     *
     * @return  Finder
     */
    public function appendDetectionCriteria(Finder $finder)
    {
        $finder->name('index.php');
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
        $fileName = $file->getFilename();

        if ($fileName !== "index.php") {
            return false;
        }

        if (stripos($file->getContents(), '"/.cm4all/include/base.php"') === false) {
	    return false;
        }

	//print_r ($file->getPathInfo());
	//print_r ($file->getPathInfo()->getPathName());
	//print_r ("\n");
	// if there is no base.php in the .cm4all subdirectory it is not a cm4all toplevel dir
	if (!is_readable($file->getPathInfo()->getPathName() . "/.cm4all/include/base.php")) {
	    return false;
	}

        $path = new \SplFileInfo($file->getPathInfo()->getPathName());

        // Return result if working
        return new System($this->getName(), $path);
    }

    /**
     * determine existence of CM4all Sites installation within a specified path
     *
     * @param   \SplFileInfo  $path  directory where the system is installed
     *
     * @return  null|string (always 1.0 for detected Sites installation)
     */
    public function detectVersion(\SplFileInfo $path)
    {
        foreach ($this->versions as $version) {
	    //printf("checking path: %s\n", $path);
            $indexFile = $path->getRealPath() . $version['indexname'];
            $cm4allIncludeFile = $path->getRealPath() . $version['cm4allincludename'];

            if (!file_exists($indexFile)) {
		//printf("missing indexFile %s\n", $indexFile);
                continue;
            }

            if (!file_exists($cm4allIncludeFile)) {
		//printf("missing cm4allIncludeFile %s\n", $cm4allIncludeFile);
                continue;
            }

            if (!is_readable($indexFile)) {
                throw new \RuntimeException(sprintf("Unreadable version information file %s", $indexFile));
            }

	    // no version information so far, so just return latest known version if Sites is detected...
            if (preg_match($version['regexp'], file_get_contents($indexFile), $matches)) {
		return "2.3";
            }
        }

        // this must not happen usually
        return null;
    }

    /***
     * @return string
     */
    public function getName()
    {
        return 'CM4all Sites';
    }

    /**
     * @InheritDoc
     */
    public function detectModules(\SplFileInfo $path)
    {
        // TODO implement this function
        return false;
    }
}
