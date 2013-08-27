<?php
/**
 * @package     Slideshow
 * @subpackage  slideshow
 * @copyright   Copyright (C) 2013 Rene Bentes Pinto, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see http://www.gnu.org/licenses/gpl-2.0.html
 */

$doc = JFactory::getDocument();
if ($params->get('enable_bootstrap'))
{
	$doc->addStyleSheet(JUri::root() . '/media/mod_slideshow/css/bootstrap-responsive.min.css');
	$doc->addStyleSheet(JUri::root() . '/media/mod_slideshow/css/bootstrap.min.css');
	$doc->addScript(JUri::root() . '/media/mod_slideshow/js/bootstrap.min.js');
	//$doc->addScript(JUri::root() . '/media/mod_slideshow/js/jquery-1.10.2.min.js');

}

// No direct access.
defined('_JEXEC') or die;

?>
<div id="slideshow<?php echo $module->id;?>" class="carousel slide">
<?php if ($params->get('count') > 0) : ?>
	<ol class="carousel-indicators">
	<?php for($i = 0; $i < $params->get('count'); $i++) : ?>
	  <li data-target="#slideshow<?php echo $module->id;?>" data-slide-to="<?php echo $i ?>"<?php echo $i == 0 ? 'class="active"' : null;?>></li>
	<?php endfor; ?>
	</ol>
<?php endif; ?>
	<div class="carousel-inner">
	<?php  foreach ($list as $i => $item) : ?>
		<div class="item<?php echo $i == 0 ? ' active' : null; ?>">
			<?php echo $item->slide; ?>
		<?php if($params->get("display_caption", 1)) : ?>
			<div class="carousel-caption">
		    	<h4><?php echo $item->title; ?></h4>
		    	<p>
		    		<?php echo JHtml::_('string.truncate', $item->introtext, $params->get('description_chars')); ?>
		    	</p>
		    </div>
		<?php endif; ?>
		</div>
	<?php endforeach; ?>
	</div>
<?php if($params->get("display_arrows", 1)): ?>
	<a class="left carousel-control" href="#slideshow<?php echo $module->id;?>" data-slide="prev">&lsaquo;</a>
	<a class="right carousel-control" href="#slideshow<?php echo $module->id;?>" data-slide="next">&rsaquo;</a>
<?php endif; ?>
</div>