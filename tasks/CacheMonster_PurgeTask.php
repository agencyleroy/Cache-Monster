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

class CacheMonster_PurgeTask extends BaseTask
{

	// Properties
	// =========================================================================

	/**
	 * @var
	 */
	private $_paths;

	private $pluginSettings;


	// Public Methods
	// =========================================================================


	/**
	 * @inheritDoc ITask::getDescription()
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return Craft::t('Purging the Varnish cache');
	}

	/**
	 * @inheritDoc ITask::getTotalSteps()
	 *
	 * @return int
	 */
	public function getTotalSteps()
	{
		// Get the actual paths out of the settings
		$paths = $this->model->getAttribute('settings')['paths'];

		$this->pluginSettings = craft()->plugins->getPlugin('cacheMonster')->getSettings();

		// Make our internal paths array
		$this->_paths = array();

		// Split the $paths array into chunks of 20 - each step
		// will be a batch of 20 requests
		$this->_paths = array_chunk($paths, 20);

		// Count our final chunked array
		return count($this->_paths);
	}

	/**
	 * @inheritDoc ITask::runStep()
	 *
	 * @param int $step
	 *
	 * @return bool
	 */
	public function runStep($step)
	{

		// NOTE: Perhaps much of this should be moved into a service

		$batch = \Guzzle\Batch\BatchBuilder::factory()
						->transferRequests(20)
						->bufferExceptions()
						->build();

		$servers = $this->pluginSettings->servers;

		// Make the client
		$client = new \Guzzle\Http\Client();

		// Set the Accept header
		$client->setDefaultOption('headers/Accept', '*/*');

		// Loop the paths in this step
		foreach ($this->_paths[$step] as $path)
		{
			foreach ($servers as $server)
			{
				$varnish = $server[0];

				// Make the url, stripping 'site:' from the path if it exists
				$path['uri'] = preg_replace('/site:/', '', $path['uri'], 1);

				$request = $client->createRequest('PURGE', $varnish . '/' . $path['uri'], array(
					'timeout'         => 5,
					'connect_timeout' => 1
				));

				$request->getCurlOptions()->set(CURLOPT_CONNECTTIMEOUT, 5);
				$request->getCurlOptions()->set(CURLOPT_CONNECTTIMEOUT_MS, 1000);
				$request->setHeader('Host', $path['host']);

				// Add it to the batch
				$batch->add($request);
			}
		}

		// Flush the queue and retrieve the flushed items
		$requests = $batch->flush();

		// Log any exceptions
		foreach ($batch->getExceptions() as $e)
		{
			Craft::log('CacheMonster: an exception occurred: '.$e->getMessage(), LogLevel::Error);
		}

		// Clear any exceptions
		$batch->clearExceptions();

		return true;

	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'paths'  => AttributeType::Mixed
		);
	}

	// Private Methods
	// =========================================================================

}
