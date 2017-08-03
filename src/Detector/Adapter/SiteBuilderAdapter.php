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
 * Class SiteBuilderAdapter
 * @package Cmsgarden\Cmsscanner\Detector\Adapter
 *
 * @since   1.0.0
 * @author Thomas Linder <thomas.linder@green.ch>
 */
class SiteBuilderAdapter implements AdapterInterface
{

    /**
     * Version detection information for SiteBuilderAdapter
     * Actually, version will be Unknown but this is how we detect CM4all Sites for now
     * @var array
     */
    protected $versions = array(
        array(
            'indexname' => '/index.html',
            'regexp' => '\<meta name="GENERATOR" content=".*SWsoft.*"\>\<meta name="ID" content=".*sitebuilder_([[:digit:]]+).*"\>'
        ),
    );

    /**
     * Just use index.html to check GENERATOR / ID
     *
     * @param   Finder  $finder  finder instance to append the criteria
     *
     * @return  Finder
     */
    public function appendDetectionCriteria(Finder $finder)
    {
        $finder->name('index.html');
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

        if ($fileName !== "index.html") {
            return false;
        }

	// if index.html does not contain sitebuilder, this is not considered a sitebuilder installation
        if (stripos($file->getContents(), 'sitebuilder') === false) {
	    return false;
        }

	//print_r ($file->getPathInfo());
	//print_r ($file->getPathInfo()->getPathName());
	//print_r ("\n");

        $path = new \SplFileInfo($file->getPathInfo()->getPathName());

        // Return result if working
        return new System($this->getName(), $path);
    }

    /**
     * determine "version" of sitebuilder installation within a specified path
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

            if (!file_exists($indexFile)) {
		//printf("missing indexFile %s\n", $indexFile);
                continue;
            }

            if (!is_readable($indexFile)) {
                throw new \RuntimeException(sprintf("Unreadable version information file %s", $indexFile));
            }

	    // no version information so far, so just return latest known version if Sites is detected...
            if (preg_match($version['regexp'], file_get_contents($indexFile), $matches)) {
		return "1.0";
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
        return 'SiteBuilder';
    }
}
