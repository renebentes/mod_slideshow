<?php
/**
 * @package     Slideshow
 * @subpackage  mod_slideshow
 * @copyright   Copyright (C) 2013 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see http://www.gnu.org/licenses/gpl-2.0.html
 */

// No direct access.
defined('_JEXEC') or die;

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

		var_dump($params);
		die;
		$model->setState('params', $appParams);

		// Get an instance of the generic articles model
		$model = JModelLegacy::getInstance('Articles', 'ContentModel', array('ignore_request' => true));

		// Set the filters based on the module params
		$model->setState('list.start', 0);
		$model->setState('list.limit', (int) $params->get('count', 5));
		$model->setState('filter.published', 1);

		// Access filter
		$access     = !JComponentHelper::getParams('com_content')->get('show_noauth');
		$authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));
		$model->setState('filter.access', $access);

		// Category filter
		$model->setState('filter.category_id', $params->get('catid', array()));

		// User filter
		$userId = JFactory::getUser()->get('id');
		switch ($params->get('user_id'))
		{
			case 'by_me':
				$model->setState('filter.author_id', (int) $userId);
				break;
			case 'not_me':
				$model->setState('filter.author_id', $userId);
				$model->setState('filter.author_id.include', false);
				break;

			case '0':
				break;

			default:
				$model->setState('filter.author_id', (int) $params->get('user_id'));
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
		);

		$ordering = JArrayHelper::getValue($order_map, $params->get('ordering'), 'a.publish_up');
		$dir = 'DESC';

		$model->setState('list.ordering', $ordering);
		$model->setState('list.direction', $dir);

		$items = $model->getItems();

		foreach ($items as &$item)
		{
			$item->slug = $item->id . ':' . $item->alias;
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
		}

		return $items;
	}

	/**
	 * Method for resizing images.
	 *
	 * @param   string   $image   The path to full image
	 * @param   string   $size    The new size. Example: array('50x50','120x250');
	 * @param   string   $folder  The thumbs destination folder
	 * @param   integer  $method  The thumbnail creation method.
	 * @return  string   Path of the new image file.
	 *
	 * @since   2.5
	 */
	public static function getImage($image, $size, $folder, $method = 2)
	{
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.image.image');

		if(!empty($size) && JFile::exists($image))
		{
			// Check or try to create folder
			if (JFolder::exists($folder) || JFolder::create($folder))
			{
				// Create file to previne direct access
				$data = "<html>\n<body bgcolor=\"#FFFFFF\">\n</body>\n</html>";
				JFile::write($folder . DS . "index.html", $data);
				// Get thumb size
				$size = explode('x', strtolower($size));
				if (count($size) != 2)
				{
					return false;
				}

				$width = $size[0];
				$height = $size[1];

				// Source object
				$sourceImage = new JImage($image);
				$srcHeight = $sourceImage->getHeight();
				$srcWidth = $sourceImage->getWidth();
				$properties = JImage::getImageFileProperties($image);

				// Generate thumb name
				$filename = JFile::getName($image);
				$extension = JFile::getExt($filename);
				$thumbname = str_replace('.' . $extension, '_' . $width . 'x' . $height . '.' . $extension, $filename);

				// Try to generate the thumb
				if ($method == 4)
				{
					// Auto crop centered coordinates
					$left = round(($srcWidth - $width) / 2);
					$top = round(($srcHeight - $height) / 2);

					// Crop image
					$thumb = $sourceImage->crop($width, $height, $left, $top, true);
				}
				else
				{
					// Resize image
					$thumb = $sourceImage->resize($width, $height, true, $method);
				}

				if ($properties->type == 3)
				{
					$quality = 10;
				}
				elseif ($properties->type == 2)
				{
					$quality = 90;
				}

				$thumbname = $folder . '/' . $thumbname;

				if (!JFile::exists($thumbname))
				{
					$thumb->toFile($thumbname, $properties->type, array('quality' => $quality));
				}
				return $thumbname;
			}
		}

		return false;
	}
}