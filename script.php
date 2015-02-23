<?php
/**
 * @package     Slideshow
 * @subpackage  mod_slideshow
 * @copyright   Copyright (C) 2013-2015 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     MIT License; see LICENSE
 */

// No direct access.
defined('_JEXEC') or die('Restricted access!');

// Include dependencies
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

/**
 * Script file of Slideshow Module
 *
 * @package     Slideshow
 * @subpackage  mod_slideshow
 *
 * @since       3.3
 */
class Mod_SlideshowInstallerScript
{
  /**
   * Extension name
   *
   * @var string
   */
  private $_extension = 'mod_slideshow';

  /**
   * Version release
   *
   * @vare string
   */
  private $_release = '';

  /**
   * Array of sub extensions package
   *
   * @var array
   */
  private $_subextensions = array(
    'modules' => array(
    ),
    'plugins' => array(
    )
  );

  /**
   * Array of obsoletes files and folders
   *
   * @var array
   */
  private $_obsoletes = array(
    'files' => array(
    ),
    'folders' => array(
      'media/mod_slideshow'
    )
  );

  /**
   * Method to install the module
   *
   * @param JInstaller $parent
   */
  function install($parent)
  {
    echo '<p>' . JText::sprintf('MOD_SLIDESHOW_INSTALL_TEXT', $this->_release) . '</p>';
  }

  /**
   * Method to uninstall the module
   *
   * @param JInstaller $parent
   */
  function uninstall($parent)
  {
    echo '<p>' . JText::sprintf('MOD_SLIDESHOW_UNINSTALL_TEXT', $this->_release) . '</p>';
  }

  /**
   * Method to update the module
   *
   * @param JInstaller $parent
   */
  function update($parent)
  {
    echo '<p>' . JText::sprintf('MOD_SLIDESHOW_UPDATE_TEXT', $this->_release) . '</p>';
  }

  /**
   * Method to run before an install/update/uninstall method
   *
   * @param string     $type Installation type (install, update, discover_install)
   * @param JInstaller $parent Parent object
   */
  function preflight($type, $parent)
  {
    $this->_checkCompatible($type, $parent);
  }

  /**
   * Method to run after an install/update/uninstall method
   *
   * @param string     $type install, update or discover_update
   * @param JInstaller $parent
   */
  function postflight($type, $parent)
  {
    $this->_removeObsoletes($this->_obsoletes);
  }

  /**
   * Method for checking compatibility installation environment
   *
   * @param  JInstaller   $parent Parent object
   *
   * @return bool         True if the installation environment is compatible
   */
  private function _checkCompatible($type, $parent)
  {
    // Get the application.
    $app            = JFactory::getApplication();
    $this->_release = (string) $parent->get('manifest')->version;
    $min_version    = (string) $parent->get('manifest')->attributes()->version;
    $jversion       = new JVersion;

    if (version_compare($jversion->getShortVersion(), $min_version, 'lt' ))
    {
      $app->enqueueMessage(JText::sprintf('MOD_SLIDESHOW_VERSION_UNSUPPORTED', $this->_release, $min_version), 'error');
      return false;
    }

    // Storing old release number for process in postflight.
    if ($type == 'update')
    {
      $oldRelease = $this->getParam('version');

      if (version_compare($this->_release, $oldRelease, 'le'))
      {
        $app->enqueueMessage(JText::sprintf('MOD_SLIDESHOW_UPDATE_UNSUPPORTED', $this->oldRelease, $this->_release), 'error');
        return false;
      }
    }
  }

  /**
   * Removes obsolete files and folders
   *
   * @param array $obsoletes
   */
  private function _removeObsoletes($obsoletes = array())
  {
    // Remove files
    if(!empty($obsoletes['files']))
    {
      foreach($obsoletes['files'] as $file)
      {
        $f = JPATH_ROOT . '/' . $file;
        if(!JFile::exists($f))
        {
          continue;
        }
        JFile::delete($f);
      }
    }

    // Remove folders
    if(!empty($obsoletes['folders']))
    {
      foreach($obsoletes['folders'] as $folder)
      {
        $f = JPATH_ROOT . '/' . $folder;
        if(!JFolder::exists($f))
        {
          continue;
        }
        JFolder::delete($f);
      }
    }
  }

  /**
   * Get a variable from the manifest cache.
   *
   * @param string $name Column name
   *
   * @return string Value of column name
   */
  private function _getParam($name)
  {
    $db    = JFactory::getDbo();
    $query = $db->getQuery(true);

    $query->select($db->quoteName('manifest_cache'));
    $query->from($db->quoteName('#__extensions'));
    $query->where($db->quoteName('name') . ' = ' . $db->quote($this->_extension));
    $db->setQuery($query);

    $manifest = json_decode($db->loadResult(), true);

    return $manifest[$name];
  }
}