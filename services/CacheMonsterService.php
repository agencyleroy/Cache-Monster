<?php
namespace Craft;

/**
 * CacheMonster by Supercool
 *
 * @package   CacheMonster
 * @author    Josh Angell
 * @copyright Copyright (c) 2015, Supercool Ltd
 * @link      http://plugins.supercooldesign.co.uk
 */

class CacheMonsterService extends BaseApplicationComponent
{

	/**
	 * [purgeElementById description]
	 *
	 * @method getPaths
	 * @param  int           $elementId the elementId of the element we want purging
	 * @return array                    an array of paths to purge
	 */
	// public function getPaths($elementId, $locale)
	public function getPaths($elementId, $initialPath, $locale)
	{
		// first path
		$purgePaths[0] = array(
			'uri'    => $initialPath
		);

		// find any sub-pages
		$branches = craft()->db->createCommand()
			->select('uri')
			->from('elements_i18n')
			// entries that are subpaths...
			->where(array('like', 'uri', $initialPath . '%'))
			// but exclude the original entry itself
			->andWhere('uri != :uri', array('uri' => $initialPath))
			->andWhere('locale = :locale', array('locale' => $locale))
			->queryColumn();

		// add the found paths to the paths to purge
		foreach ($branches as $branchUri)
		{
			$purgePaths[] = array(
				'uri'    => $branchUri
			);
		}

		// find any parent pages
		$parentPages = array();

		if (strpos($initialPath, '/') !== false)
		{
			// then there are parent pages
			$builtUpUri = '';

			// break up the uri into its parts and loop over them
			foreach (explode('/', $initialPath) as $pathPart)
			{

				// make sure that there is a slash in between each part of the path, but never at the beginning
				if (!empty($builtUpUri))
				{
					$builtUpUri .= '/';
				}

				// add one part of the path at a time
				$builtUpUri .= $pathPart;

				$parentPages[] = array(
					'uri'    => $builtUpUri
				);
			}
		}

		$purgePaths = array_merge($parentPages, $purgePaths);

		// add host, according to the locale to host mappings specified in the settings
		// maybe one day in the future we'll support purging multiple hosts at once
		$settings = craft()->plugins->getPlugin('cacheMonster')->getSettings();

		$localeHostMap = array();

		// don't flip the array, just turn it :P
		foreach ($settings->localeHosts as $host) {
			// for example  $localeHostMap['en_us'] => 'google.com'
			$localeHostMap[$host[0]] = $host[1];
		}

		foreach ($purgePaths as $key => $purgePath)
		{
			$purgePaths[$key]['host'] = $localeHostMap[$locale];
		}

		return $purgePaths;
	}


	/**
	 * Gets the sitemap then caches and returns an array of the paths found in it
	 *
	 *  TODO: let user set sitemap location(s) in the cp, default to /sitemap.xml
	 *
	 * @method crawlSitemapForPaths
	 * @return array               an array of $paths
	 */
	public function crawlSitemapForPaths()
	{

		// This might be heavy, probably not but better safe than sorry
		craft()->config->maxPowerCaptain();

		$paths = array();

		// Get the (one day specified) sitemap
		$client = new \Guzzle\Http\Client();
		$response = $client->get(UrlHelper::getSiteUrl('sitemap.xml'))->send();

		// Get the xml and add each url to the $paths array
		if ( $response->isSuccessful() )
		{
			$xml = $response->xml();

			foreach ($xml->url as $url)
			{
				$parts = parse_url((string)$url->loc);
				$paths[] = 'site:' . ltrim($parts['path'], '/');
			}
		}

		// Check $paths is unique
		$paths = array_unique($paths);

		// Return the actual paths
		return $paths;

	}


	/**
	 * Regusters a Task with Craft, taking into account if there
	 * is already one pending
	 *
	 * @method makeTask
	 * @param  string    $taskName   the name of the Task you want to register
	 * @param  array     $paths      an array of paths that should go in that Tasks settings
	 */
	public function makeTask($taskName, $paths)
	{

		// If there are any pending tasks, just append the paths to it
		$task = craft()->tasks->getNextPendingTask($taskName);

		if ($task && is_array($task->settings))
		{
			$settings = $task->settings;

			if (!is_array($settings['paths']))
			{
				$settings['paths'] = array($settings['paths']);
			}

			if (is_array($paths))
			{
				$settings['paths'] = array_merge($settings['paths'], $paths);
			}
			else
			{
				$settings['paths'][] = $paths;
			}

			// Make sure there aren't any duplicate paths
			$settings['paths'] = array_unique($settings['paths']);

			// Set the new settings and save the task
			$task->settings = $settings;
			craft()->tasks->saveTask($task, false);
		}
		else
		{
			craft()->tasks->createTask($taskName, null, array(
				'paths' => $paths
			));
		}

	}

	public function purgeUrl($host, $path)
	{
		$settings = craft()->plugins->getPlugin('cacheMonster')->getSettings();

		$servers = $settings->servers;
		if($servers && count($servers) > 0) {
			$count = count($servers);
			$return = true;

			$batch = \Guzzle\Batch\BatchBuilder::factory()
							->transferRequests($count)
							->bufferExceptions()
							->build();

			$client = new \Guzzle\Http\Client();
			$client->setDefaultOption('headers/Accept', '*/*');
			foreach($servers as $server) {
				$varnish = $server[0];

				$request = $client->createRequest('PURGE', $varnish.$path, array(
					'timeout'         => 5,
					'connect_timeout' => 1
				));

				$request->getCurlOptions()->set(CURLOPT_CONNECTTIMEOUT, 5);
				$request->getCurlOptions()->set(CURLOPT_CONNECTTIMEOUT_MS, 1000);
				$request->setHeader('Host', $host);

				$batch->add($request);
			}

			$requests = $batch->flush();

			foreach ($batch->getExceptions() as $e)
			{
				CacheMonsterPlugin::log('CacheMonster: an exception occurred: '.$e->getMessage(), LogLevel::Error);
				$return = false;
			}

			$batch->clearExceptions();

			return $return;
		}

		return false;
	}

}
