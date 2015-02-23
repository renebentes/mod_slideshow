<?php
/**
 * @package     Slideshow
 * @subpackage  slideshow
 * @copyright   Copyright (C) 2013 - 2015 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     MIT License; see LICENSE
 */

// No direct access.
defined('_JEXEC') or die;

$js   = array();
$js[] = 'jQuery.noConflict();';
$js[] = '! function($) {';
$js[] = '  $(function() {';
$js[] = '    $(\'#slideshow' . $module->id . '\').carousel({';
$js[] = '      interval: ' . $params->get('interval');
$js[] = '    });';
$js[] = '  });';
$js[] = '}(jQuery);';

JFactory::getDocument()->addScriptDeclaration(implode("\n", $js));

$itemscount = count($list);
if ($params->get('count') > 0 && $itemscount) : ?>
<div id="slideshow<?php echo $module->id;?>" class="carousel slide" data-ride="carousel">
  <ol class="carousel-indicators">
  <?php for($i = 0; $i < $itemscount; $i++) : ?>
    <li data-target="#slideshow<?php echo $module->id;?>" data-slide-to="<?php echo $i ?>"<?php echo $i == 0 ? 'class="active"' : null;?>></li>
  <?php endfor; ?>
  </ol>
  <div class="carousel-inner" role="listbox">
  <?php  foreach ($list as $i => $item) : ?>
    <div class="item<?php echo $i == 0 ? ' active' : null; ?>">
      <a href="<?php echo $item->link; ?>">
        <?php echo $item->slide; ?>
      </a>
    <?php if($params->get("display_caption", 1)) : ?>
      <div class="carousel-caption">
        <h4>
          <a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
        </h4>
        <p>
          <?php echo JHtml::_('string.truncate', $item->introtext, $params->get('description_chars'), false, false); ?>
        </p>
      </div>
    <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php if($params->get("display_arrows", 1) && $itemscount > 1): ?>
  <a class="left carousel-control" href="#slideshow<?php echo $module->id;?>" role="button" data-slide="prev">
    <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
    <span class="sr-only">Previous</span>
  </a>
  <a class="right carousel-control" href="#slideshow<?php echo $module->id;?>" role="button" data-slide="next">
    <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
    <span class="sr-only">Next</span>
  </a>
<?php endif; ?>
</div>
<?php endif; ?>