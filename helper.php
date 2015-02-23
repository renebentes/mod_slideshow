<?php
/**
 * @package     Slideshow
 * @subpackage  mod_slideshow
 * @copyright   Copyright (C) 2013 - 2015 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     MIT License; see LICENSE
 */

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.filesystem.folder');

require_once JPATH_SITE . '/components/com_content/helpers/route.php';

JModelLegacy::addIncludePath(JPATH_SITE . '/components/com_content/models', 'ContentModel');

/**
 * Slideshow module helper.
 *
 * @package     Slideshow
 * @subpackage  mod_slideshow
 * @since       2.5
 */
abstract class modSlideshowHelper
{
	/**
	 * Get a list of the articles.
	 *
	 * @param   JRegistry  &$params  The module options.
	 *
	 * @return  array
	 *
	 * @since   2.5
	 */
	public static function getList(&$params)
	{
		// Get the dbo
		$db = JFactory::getDbo();

		// Set application parameters in model
		$app       = JFactory::getApplication();
		$appParams = $app->getParams();

		// Get an instance of the generic articles model
		$model = JModelLegacy::getInstance('Articles', 'ContentModel', array('ignore_request' => true));

		$model->setState('params', $appParams);

		// Set the filters based on the module params
		$model->setState('list.start', 0);
		$model->setState('list.limit', (int) $params->get('count', 5));
		$model->setState('filter.published', 1);

		$model->setState(
			'list.select',
			'a.id, a.title, a.alias, a.introtext, a.fulltext, a.catid, ' .
			'a.checked_out, a.checked_out_time, a.state, a.created, a.created_by, ' .
			'a.created_by_alias, a.modified, a.modified_by, a.publish_up, ' .
			'a.publish_down, a.images, a.urls, a.attribs, a.metadata, a.metakey, ' .
			'a.metadesc, a.access, a.hits, a.featured'
			);

		// Access filter
		$access     = !JComponentHelper::getParams('com_content')->get('show_noauth');
		$authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));
		$model->setState('filter.access', $access);

		// Filter by source param
		switch ($params->get('source'))
		{
			case 'Itemid':
				$model->setState('filter.article_id', explode(',', $params->get('Itemid')));
				break;
			case 'catid':
			default:
				$model->setState('filter.category_id', $params->get('catid', array()));
				break;
		}

		// Filter by language
		$model->setState('filter.language', $app->getLanguageFilter());

		//  Featured switch
		switch ($params->get('show_featured'))
		{
			case '1':
				$model->setState('filter.featured', 'only');
				break;
			case '0':
				$model->setState('filter.featured', 'hide');
				break;
			default:
				$model->setState('filter.featured', 'show');
				break;
		}

		// Set ordering
		$order_map = array(
			'm_dsc' => 'a.modified DESC, a.created',
			'mc_dsc' => 'CASE WHEN (a.modified = ' . $db->quote($db->getNullDate()) . ') THEN a.created ELSE a.modified END',
			'c_dsc' => 'a.created',
			'p_dsc' => 'a.publish_up',
			'random' => 'RAND()',
		);

		$ordering = JArrayHelper::getValue($order_map, $params->get('ordering'), 'a.publish_up');
		$dir = 'DESC';

		$model->setState('list.ordering', $ordering);
		$model->setState('list.direction', $dir);

		$items = $model->getItems();

		foreach ($items as &$item)
		{
			$item->slug    = $item->id . ':' . $item->alias;
			$item->catslug = $item->catid . ':' . $item->category_alias;

			if ($access || in_array($item->access, $authorised))
			{
				// We know that user has the privilege to view the article
				$item->link = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catslug));
			}
			else
			{
				$item->link = JRoute::_('index.php?option=com_users&view=login');
			}

			self::_getImage($item);

			if (!empty($item->slide))
			{
				$file = self::_getThumbnail($item->slide, $params->get('width') . 'x' . $params->get('height'), 'cache/mod_slideshow', $item->id, $params->get('quality'));
				$item->slide = '<img class="img-responsive center-block" src="' . $file .'" title="' . $item->title . '" alt="' . $item->title .'" />';
			}
			else
			{
				$holder = array(JPATH_SITE . '/'. $app->getTemplate(false) . '/js', JPATH_SITE . '/' . $app->getTemplate(false) . '/assets/js');
				jimport('joomla.filesystem.path');
				if (JPath::find($holder, 'holder.js'))
				{
					$item->slide = '<img class="img-responsive center-block" data-src="' . JPath::find($holder, 'holder.js') . '" title="' . $item->title . '" alt="' . $item->title .'" />';
				}
			}
		}

		return $items;
	}

	/**
	 * Gets the first occurrence of this images in articles
	 *
	 * @param  array $item Array with data item
	 *
	 * @return array       Array with data item including an image display
	 */
	private static function _getImage($item)
	{
		jimport('joomla.filesystem.folder');

		$images = json_decode($item->images);
		if (isset($images->image_intro) && isset($images->image_fulltext))
		{
			if (!empty($images->image_fulltext))
			{
				$item->slide = $images->image_fulltext;
			}
			elseif (!empty($images->image_intro)) {
				$item->slide = $images->image_intro;
			}
		}

		if(empty($item->slide))
		{
			$regex = "/\<img.+src\s*=\s*\"([^\"]*)\"[^\>]*\>/";
			preg_match($regex, $item->introtext, $matches);
			if (count($matches))
			{
				$item->slide = $matches[1];
			}
			else
			{
				preg_match($regex, $item->fulltext, $matches);
				$item->slide = count($matches) ? $matches[1] : '';
			}
		}

		if (empty($item->slide))
		{
			$regex = "/{gallery}(.+?){\/gallery}/is";
			preg_match($regex, $item->introtext, $matches);
			if (count($matches))
			{
				$item->slide = $matches[1];
			}
			else
			{
				preg_match($regex, $item->fulltext, $matches);
				$item->slide = count($matches) ? $matches[1] : '';
			}

			$files = JFolder::files(JPATH_SITE . '/images/' . $item->slide);
			if (!$files)
			{
				return $item;
			}

			$fileTypes = array('jpg', 'jpeg', 'gif', 'png');
			foreach ($files as $file)
			{
				$fileInfo = pathinfo($file);
				if (array_key_exists('extension', $fileInfo) && in_array(strtolower($fileInfo['extension']), $fileTypes))
				{
					$item->slide = JPATH_SITE . '/images/' . $item->slide . '/' . $file;
					break;
				}
			}
		}

		return $item;
	}

	/**
	 * Get the thumbnail path.
	 *
	 * @param  string  $image    Path to source image
	 * @param  string  $size     The thumbnail size. Ex: '500x400'
	 * @param  string  $folder   The thumbnail folder
	 * @param  string  $filename The thumbnail file name
	 * @param  integer quality   The thumbnail quality
	 *
	 * @return string            The thumbnail path
	 *
	 * @throws ArgumentException
	 * @throws InvalidArgumentException
	 */
	private static function _getThumbnail($image, $size = '550x460', $folder = null, $filename = null, $quality = 90)
	{
		jimport('joomla.filesystem.file');
		require_once __DIR__ . '/libraries/phpthumb/ThumbLib.inc.php';

		if (empty($image))
		{
			throw new ArgumentException(JText::_("MOD_SLIDESHOW_ERROR_ARGUMENT_IMAGE_EMPTY"));
		}

		if (empty($folder))
		{
			$folder = 'cache/mod_slideshow';
		}

		$folder = JPATH_SITE . '/' . $folder;

		// Check or try to create folder
		if (JFolder::exists($folder) || JFolder::create($folder))
		{

			// Desired thumbnail size
			$size = explode('x', strtolower($size));

			if (count($size) != 2)
			{
				throw new InvalidArgumentException(JText::sprintf('MOD_SLIDESHOW_ERROR_ARGUMENT_SIZE_INVALID', $size));
			}

			// Generate thumb name
			$filename  = empty($filename) ? pathinfo($image, PATHINFO_FILENAME) : $filename;
			$extension = pathinfo($image, PATHINFO_EXTENSION);

			$filename = $filename . '.' . $extension;
			$file     = $folder . '/' . $filename;

			$image = new JImage($image);

			try
			{
				$thumbnail = $image->cropResize($size[0], $size[1], false);
				$thumbnail->toFile($folder . '/' . $filename);
			}
			catch (Exception $e)
			{
				JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');
			}

			/*$thumbnail = PhpThumbFactory::create($image);
			$thumbnail->setoptions(array('jpegQuality' => $quality,'resizeUp' => true));
			$thumbnail->adaptiveResize($size[0], $size[1]);
			$thumbnail->save($file);*/
		}

		return str_replace(JPATH_SITE . '/', JUri::root(), $file);
	}
}