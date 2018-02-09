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
 * Class CM4allWebSiteCreatorAdapter
 * @package Cmsgarden\Cmsscanner\Detector\Adapter
 *
 * @since   1.0.0
 * @author Thomas Linder <thomas.linder@green.ch>
 */
class CM4allWebSiteCreatorAdapter implements AdapterInterface
{

    /**
     * Version detection information for CM4allSitesAdapter
     * Actually, version will be Unknown but this is how we detect CM4all Sites for now
     * @var array
     */
    protected $versions = array(
        array(
            'indexname' => '/index.html',
            'regexp' => '#<meta name="GENERATOR" content="www\.cm4all\.com"><TITLE>#'
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

        //print ("detectSystem: Path info\n");
        //print_r ($file->getPathInfo());
        //print_r ($file->getPathInfo()->getPathName());
        //print_r ("\n");
        if (stripos($file->getContents(), '<meta name="GENERATOR" content="www.cm4all.com"><TITLE>') === false) {
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
     * @return  null|string (1.0.2015 pseudo version with year of latest update)
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
                return "1.0.2015";
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
        return 'CM4all WebSite Creator';
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
