<?php
/**
 * @package    CMSScanner
 * @copyright  Copyright (C) 2014 CMS-Garden.org
 * @license    GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link       http://www.cms-garden.org
 */

namespace Cmsgarden\Cmsscanner\Command;

use Cmsgarden\Cmsscanner\Detector\System;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class DetectCommand
 * @package Cmsgarden\Cmsscanner\Command
 *
 * @since   1.0.0
 */
class DetectCommand extends AbstractDetectionCommand
{
    /**
     * configure this console command
     */
    protected function configure()
    {
        $this
            ->setName('cmsscanner:detect')
            ->setDescription('Detects all CMS installations in a given path')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Directories where CMS should be detected'
            )
            ->addOption(
                'depth',
                null,
                InputOption::VALUE_REQUIRED,
                'If set, the detector will limit the directory recursion to the specified level'
            )
            ->addOption(
                'versions',
                null,
                InputOption::VALUE_NONE,
                'If set, the detector will determine the used version'
            )
            ->addOption(
                'report',
                null,
                InputOption::VALUE_REQUIRED,
                'Write a detailed JSON report to the specified path'
            )
            ->addOption(
                'readfromfile',
                null,
                InputOption::VALUE_NONE,
                'Read \\0 separated target directories from a file, passed as the argument'
            )
        ;
    }

    /**
     * execute this command
     *
     * @param   InputInterface   $input   CLI input data
     * @param   OutputInterface  $output  CLI output data
     *
     * @return  int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = microtime(true);

        // Create a new Finder instance
        $finder = new Finder();

        // Search for files in the directory specified in the CLI argument
        $finder->files()->followLinks();

        // Get paths to scan in; Either from file or from the CLI arguments
        if ($input->getOption('readfromfile')) {
            $paths = $input->getArgument('paths');
            $pathFile = reset($paths);
            $paths = $this->readPathsFromFile($pathFile);
        } else {
            $paths = $input->getArgument('paths');
        }

        foreach ($paths as $path) {
            $finder->in($path);
        }

        // Limit search to recursion level
        if ($input->getOption('depth')) {
            $finder->depth("<= " . $input->getOption('depth'));
        }

        // Append system specific search criteria
        foreach ($this->adapters as $adapterName => $adapter) {
            $finder = $adapter->appendDetectionCriteria($finder);
        }

        $results = array();

        // Iterate through results
        foreach ($finder as $file) {
            // Iterate through system adapters
            foreach ($this->adapters as $adapterName => $adapter) {
                // Pass the search result to the system adapter to verify the result
                if (!$system = $adapter->detectSystem($file)) {
                    // search result doesn't match with system
                    continue;
                }

                // If enabled, try to determine the used CMS version
                if ($input->getOption('versions')) {
                    $system->version = $adapter->detectVersion($system->getPath());
                }

                // Append successful result to array
                $results[] = $system;
            }
        }

        // Generate stats array
        $stats = $this->generateStats($results, $input->getOption('versions'));

        // Write output to command line
        $output->writeln('<info>Successfully finished scan!</info>');
        $output->writeln(sprintf('CMSScanner found %d CMS installations!', count($results)));

        // Write stats to command line
        $this->outputStats($stats, $input->getOption('versions'), $output);

        // Write report file
        if ($input->getOption('report')) {
            $this->writeReport($results, $input->getOption('report'));
            $output->writeln(sprintf("Report was written to %s", $input->getOption('report')));
        }

        $this->outputProfile($startTime, $output);
    }

    /**
     * generate stats array from results
     *
     * @param   System[]  $results       results returned from system adapters
     * @param   bool      $versionStats  generate version stats
     *
     * @return  array
     */
    protected function generateStats(array $results, $versionStats)
    {
        $stats = array();

        foreach ($results as $result) {
            $systemName = $result->getName();

            // Create stats array for each system
            if (empty($stats[$systemName])) {
                $stats[$systemName] = array(
                    'amount' => 0,
                    'versions' => array(),
                );
            }

            $stats[$systemName]['amount']++;

            // Increase count for this used version
            if ($versionStats) {
                if (!$result->version) {
                    if (!array_key_exists('Unknown', $stats[$systemName]['versions'])) {
                        $stats[$systemName]['versions']['Unknown'] = 0;
                    }

                    $stats[$systemName]['versions']['Unknown']++;

                    continue;
                }

                if (empty($stats[$systemName]['versions'][$result->version])) {
                    $stats[$systemName]['versions'][$result->version] = 0;
                }

                $stats[$systemName]['versions'][$result->version]++;
            }
        }

        return $stats;
    }

    /**
     * output stats to command line
     *
     * @param   array            $stats         stats data
     * @param   bool             $versionStats  output version stats
     * @param   OutputInterface  $output        cli output
     */
    protected function outputStats(array $stats, $versionStats, OutputInterface $output)
    {
        $output->writeln("");

        $table = new Table($output);
        $table->setHeaders(array('CMS', '# Installations'));

        foreach ($stats as $system => $cmsStats) {
            $table->addRow(array($system, $cmsStats['amount']));
        }

        $table->render();

        // Render version stats if enabled
        if ($versionStats) {
            $output->writeln("");
            $output->writeln("<info>Version specific stats:</info>");

            foreach ($stats as $system => $cmsStats) {
                $output->writeln(sprintf("%s:", $system));

                $table = new Table($output);
                $table->setHeaders(array('Version', '# Installations'));

                uksort(
                    $cmsStats['versions'],
                    function ($a, $b) {
                        return version_compare($a, $b);
                    }
                );

                foreach ($cmsStats['versions'] as $version => $amount) {
                    $table->addRow(array($version, $amount));
                }

                $table->render();
            }
        }
    }

    /**
     * output stats to command line
     *
     * @param   float            $startTime     microtime where execution has started
     * @param   OutputInterface  $output        cli output
     */
    protected function outputProfile($startTime, OutputInterface $output)
    {
        $output->writeln("");

        $endTime = microtime(true);

        $output->writeln('Execution time: ' . ($endTime - $startTime) . ' seconds');
        $output->writeln('Memory consumption: ' . self::parseSizeUnit(memory_get_usage(true)));
    }

    /**
     * converts the results into a JSON and write it to a file
     *
     * @param   System[]   $results  result data
     * @param   string     $path     target path
     */
    protected function writeReport(array $results, $path)
    {
        // we need this to convert the \SplFileInfo object into a normal path string
        array_walk($results, function (&$result, $key) {
		$encoding_list = array("CP1252", "ISO-8859-1", "ASCII");
		$realpath = $result->getPath()->getRealPath();
		//printf("** writing %s:\n", print_r($result, true));
		if (! mb_check_encoding($realpath, "UTF-8")) {
		    //printf("----: encoding list: %s\n", print_r($encoding_list, true));
		    //printf("OOPS: wrong encoding for %s\n", $realpath);
		    if (($encoding = mb_detect_encoding($realpath, $encoding_list, false)) === false) {
			//printf("OOPS: unable to detect encoding of %s\n", $realpath);
			$realpath = null;
		    } else {
			//printf("NOTE: Encoding detected for %s: %s\n", $realpath, $encoding);
			$realpath = mb_convert_encoding($realpath, "UTF-8", $encoding);
		    }
		}
		//printf("INFO: Path: %s\n", $realpath);
		//printf("INFO: Path (mb coverted encoded): %s\n", mb_convert_encoding(json_encode((object) $realpath), "UTF-8"));
		//printf("INFO: Path (encoded unescaped): %s\n", mb_convert_encoding(json_encode((object) $realpath, JSON_UNESCAPED_UNICODE), "UTF-8"));
		//printf("INFO: Path (encoded): %s\n", json_encode((object) $realpath), "UTF-8");
                $result = array(
                    "name" => $result->getName(),
                    "version" => $result->getVersion(),
                    "path" => $realpath
                );
        });

        if (file_put_contents($path, json_encode($results)) === false) {
            throw new \RuntimeException("Could not write to report file");
        }
    }

    /**
     * Extracts paths to scan in from an input file
     *
     * @param $pathsFile
     *
     * @return array
     */
    protected function readPathsFromFile($pathsFile)
    {
        if (!file_exists($pathsFile) || !is_readable($pathsFile)) {
            throw new \InvalidArgumentException("Can not read paths file");
        }

        $fileContent = file_get_contents($pathsFile);

        $paths = explode("\0", $fileContent);

        return $paths;
    }

    /**
     * Creates the rounded size of the size with the appropriate unit
     *
     * @param   float   $bytes  The maximum size which is allowed for the uploads
     *
     * @return  string  String with the size and the appropriate unit
     */
    private static function parseSizeUnit($bytes)
    {
        $base     = log($bytes) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), 2) . $suffixes[floor($base)];
    }
}
