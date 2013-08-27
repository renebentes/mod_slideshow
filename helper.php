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
		switch ($params->get('source')) {
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

			self::_getImage($item);

			if (!empty($item->slide))
			{
				$file = self::_getThumbnail($item->slide, $params->get('width') . 'x' . $params->get('height'), 'cache/mod_slideshow', $item->id, $params->get('method'), $params->get('quality'));
				$item->slide = '<img src="' . str_replace(JPATH_SITE . '/', JUri::root(), $file) .'" title="' . $item->title . '" alt="' . $item->title .'" />';
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

		return $item;
	}

	/**
	 * Method for resizing images.
	 *
	 * @param   string   $image   	The path to full image
	 * @param   string   $size    	The new size. Example: array('50x50','120x250');
	 * @param   string   $folder  	The thumbnail destination folder
	 * @param 	string   $filename 	The thumbnail filename
	 * @param   boolean  $method  	The thumbnail smart resize.
	 * @param   integer  quality 	The quality of the thumbnail creation
	 * @return  mixed 				Path of the new image file.
	 *
	 * @since   2.5
	 */
	private static function _getThumbnail($image, $size = '550x460', $folder, $filename = '', $method = true, $quality = 95)
	{
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.image.image');

		$fileTypes = array('jpg', 'jpeg', 'gif', 'png');
		$folder = JPATH_SITE . '/' . $folder;

		if(JFile::exists($image))
		{
			// Check or try to create folder
			if (JFolder::exists($folder) || JFolder::create($folder))
			{
				// Create file to previne direct access
				$data = "<html>\n<body bgcolor=\"#FFFFFF\">\n</body>\n</html>";
				JFile::write($folder . "/index.html", $data);

				$sourceName = JFile::getName($image);
				$extension = JFile::getExt($sourceName);
				$filename = empty($filename) ? $sourceName : $filename . '.' .$extension;

				if (!in_array(strtolower($extension), $fileTypes))
				{
					return false;
				}

				// Determine thumb image filename
				if (strtolower(substr($filename, -4, 4)) == 'jpeg')
				{
					$thumbname = substr($filename, 0, -4) . 'jpg';
				}
				elseif (strtolower(substr($filename, -3, 3)) == 'gif' || strtolower(substr($filename, -3, 3)) == 'png' || strtolower(substr($filename, -3, 3)) == 'jpg')
				{
					$thumbname = substr($filename, 0, -3) . 'jpg';
				}

				//$thumbname = str_replace('.jpg', '_' . $width . 'x' . $height . '.jpg' . $extension, $filename);
				$thumbname = $folder . '/' . $thumbname;

				// begin by getting the details of the original
				list($width, $height, $type) = getimagesize(JPATH_SITE . '/' . $image);

				// create an image resource for the original
				switch($type)
				{
					case 1 :
						$source = @ imagecreatefromgif($image);
						if (!$source)
						{
							throw new Exception(JText::_('MOD_SLIDESHOW_ERROR_GIF'), 500);
						}
						break;
					case 2 :
						$source = imagecreatefromjpeg($image);
						break;
					case 3 :
						$source = imagecreatefrompng($image);
						break;
					default :
						$source = NULL;
				}

				if (!$source)
				{
					throw new Exception(JText::_('MOD_SLIDESHOW_ERROR_IMAGES'), 500);
				}

				// Get thumb size
				$size = explode('x', strtolower($size));
				if (count($size) != 2)
				{
					return false;
				}

				// calculate thumbnails
				$thumbnail = self::_getDimension($width, $height, $size[0], $size[1], $method);

				// create an image resource for the thumbnail
				$thumb = imagecreatetruecolor($thumbnail['width'], $thumbnail['height']);

				// create the resized copy
				imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbnail['width'], $thumbnail['height'], $width, $height);

				// convert and save all thumbs to .jpg
				$success = imagejpeg($thumb, $thumbname, $quality);

				// Bail out if there is a problem in the GD conversion
				if (!$success)
					return false;

				// remove the image resources from memory
				imagedestroy($source);
				imagedestroy($thumb);
				return $thumbname;
			}
		}

		return false;
	}

	/**
	 * Calculate thumbnail dimensions
	 *
	 * @param  integer $srcWidth    Width of the source image.
	 * @param  integer $srcHeight   Height of the source image.
	 * @param  integer $thbWidth   	Width of the thumbnail.
	 * @param  integer $thbHeight  	Height of the source image.
	 * @param  boolean $smartResize The thumbnail smart resize.
	 *
	 * @return array              	Array with the dimensions of the thumbnail.
	 */
	private static function _getDimension($srcWidth, $srcHeight, $thbWidth, $thbHeight, $smartResize)
	{
		if ($smartResize)
		{
			// thumb ratio bigger that container ratio
			if ($srcWidth / $srcHeight > $thbWidth / $thbHeight)
			{
				$thumb_width = $thbHeight * $srcWidth / $srcHeight;
				$thumb_height = $thbHeight;
			}
			else
			{
				// wide containers
				if ($thbWidth >= $thbHeight)
				{
					$thumb_width = $thbWidth;
					$thumb_height = $thbWidth * $srcHeight / $srcWidth;
				}
				else
				{
					// wide thumbs
					if ($srcWidth > $srcHeight)
					{
						$thumb_width = $thbHeight * $srcWidth / $srcHeight;
						$thumb_height = $thbHeight;
					}
					// high thumbs
					else
					{
						$thumb_width = $thbWidth;
						$thumb_height = $thbWidth * $srcHeight / $srcWidth;
					}
				}
			}

		}
		else
		{

			if ($srcWidth > $height)
			{
				$thumb_width = $thbWidth;
				$thumb_height = $thbWidth * $srcheight / $srcWidth;
			}
			elseif ($srcWidth < $srcheight)
			{
				$thumb_width = $thbHeight * $srcWidth / $srcheight;
				$thumb_height = $thbHeight;
			}
			else
			{
				$thumb_width = $thbWidth;
				$thumb_height = $thbHeight;
			}

		}

		$thumbnail = array();
		$thumbnail['width'] = round($thumb_width);
		$thumbnail['height'] = round($thumb_height);

		return $thumbnail;

	}
}