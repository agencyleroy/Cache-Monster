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

class CacheMonsterPlugin extends BasePlugin
{

	// Properties
	// =========================================================================

	/**
	 * @var
	 */
	private $_settings;

	// Public Methods
	// =========================================================================

	public function getName()
	{
		return Craft::t('CacheMonster');
	}

	public function getVersion()
	{
		return '0.9';
	}

	public function getDeveloper()
	{
		return 'Supercool';
	}

	public function getDeveloperUrl()
	{
		return 'http://plugins.supercooldesign.co.uk';
	}

	public function init()
	{

		/**
		 * Get plugin settings
		 */
		$plugin = craft()->plugins->getPlugin('cachemonster');
		$this->_settings = $plugin->getSettings();

		if ($this->_settings->uiWidget)
		{
			$this->initUIWidget();
		}

		/**
		 * Before we save, grab the paths that are going to be purged
		 * and save them to a cache
		 */
		craft()->on('elements.onBeforeSaveElement', function(Event $event)
		{

			// Get the element ID
			$element = $event->params['element'];

			$initialPath = $element->uri;

			// for some reason, this function gets called twice, once with an empty path. we're not interested in that.
			if (empty($initialPath)) return;

			$elementId = $element->id;
			$locale = $element->locale;

			// If we have an element, go ahead and get its paths
			if ($elementId)
			{
				// Clear our cacheMonsterPaths cache, just in case
				craft()->cache->delete('cacheMonsterPaths-' . $elementId . '-' . $locale);

				// Get the paths we need
				$paths = craft()->cacheMonster->getPaths($elementId, $initialPath, $locale);

				if ($paths)
				{
					// Store them in the cache so we can get them after
					// the element has actually saved
					craft()->cache->set('cacheMonsterPaths-' . $elementId . '-' . $locale, $paths);
				}

			}

		});


		/**
		 * After the element has saved run the purging and warming tasks
		 */
		craft()->on('elements.onSaveElement', function(Event $event)
		{
			$element = $event->params['element'];

			$elementId = $element->id;
			$startingPath = $element->uri;
			$locale = $element->locale;


			if ($elementId)
			{
				// Remove this, as it might cause issues if its used again

				// CacheMonsterPlugin::log(print_r($paths, true), LogLevel::Error);

				// Get the paths out of the cache for that element
				$paths = craft()->cache->get('cacheMonsterPaths-' . $elementId . '-' . $locale);

				// Use those paths to purge (if on) and warm
				if ($paths)
				{
					if ($this->_settings['varnish'])
					{
						craft()->cacheMonster->makeTask('CacheMonster_Purge', $paths);

						craft()->cache->delete('cacheMonsterPaths-' . $elementId . '-' . $locale);
					}

					craft()->cacheMonster->makeTask('CacheMonster_Warm', $paths);
				}
			}
		});
	}

	public function getSettingsHtml()
	{
		return craft()->templates->render('cacheMonster/settings', array(
			'settings'    => $this->getSettings(),
			'servers'     => $this->getSettings()->servers,
			'localeHosts' => $this->getSettings()->localeHosts
		));
	}

	public function prepSettings($settings)
	{
		// Empty all servers
		if (!isset($settings['servers']))
		{
			$settings['servers'] = array();
		}

		if (!isset($settings['localeHosts']))
		{
			$settings['localeHosts'] = array();
		}

		return $settings;
	}

	protected function defineSettings()
	{
		return array(
			'varnish'        => array(AttributeType::Bool, 'default' => false),
			'uiWidget'       => array(AttributeType::Bool, 'default' => false),
			'servers'        => array(AttributeType::Mixed, 'default' => array()),
			'localeHosts'    => array(AttributeType::Mixed, 'default' => array())
		);
	}

	protected function initUIWidget()
	{
		// include this in every site request. show the widget to logged-in users with JS.
		if ( craft()->request->isSiteRequest() )
		{
			$path = craft()->request->getRequestUri();
			$host = craft()->request->getHostname();

			$actionUserLoggedIn = UrlHelper::getActionUrl('cacheMonster/userLoggedIn');
			$actionPurgeUrl = UrlHelper::getActionUrl('cacheMonster/purgeUrl', array(
				'path'=> urlencode($path),
				'host' => urlencode($host)
			));

			craft()->templates->includeCssResource('cachemonster/css/widget.css');
			craft()->templates->includeJsResource('cachemonster/js/widget.js');
			craft()->templates->includeJs('jQuery("body").cmUiWidget("' . $actionUserLoggedIn . '", "'.$actionPurgeUrl.'")');
		}
	}

}
