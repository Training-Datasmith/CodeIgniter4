<?php

use Code_Igniter\Pager\Pager_Renderer;
/**
 * @var PagerRenderer $pager
 */
$pager->set_surround_count(0);
?>
<nav>
	<ul class="pager">
		<li <?php 
echo $pager->has_previous() ? '' : 'class="disabled"';
?>>
			<a href="<?php 
echo $pager->get_previous() ?? '#';
?>" aria-label="<?php 
echo lang('Pager.previous');
?>">
				<span aria-hidden="true"><?php 
echo lang('Pager.newer');
?></span>
			</a>
		</li>
		<li <?php 
echo $pager->has_next() ? '' : 'class="disabled"';
?>>
			<a href="<?php 
echo $pager->get_next() ?? '#';
?>" aria-label="<?php 
echo lang('Pager.next');
?>">
				<span aria-hidden="true"><?php 
echo lang('Pager.older');
?></span>
			</a>
		</li>
	</ul>
</nav>
