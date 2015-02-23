<?php
/**
 * @package     Slideshow
 * @subpackage  mod_slideshow
 * @copyright   Copyright (C) 2013 - 2015 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     MIT License; see LICENSE
 */

// No direct access.
defined('_JEXEC') or die;

// Include the syndicate functions only once
require_once __DIR__ . '/helper.php';

$list            = modSlideshowHelper::getList($params);
$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));

require JModuleHelper::getLayoutPath('mod_slideshow', $params->get('layout', 'default'));