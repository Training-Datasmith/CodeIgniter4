<?php

use Code_Igniter\Pager\Pager_Renderer;
/**
 * @var PagerRenderer $pager
 */
$pager->set_surround_count(2);
?>

<nav aria-label="<?php 
echo lang('Pager.pageNavigation');
?>">
	<ul class="pagination">
		<?php 
if ($pager->has_previous()) {
    ?>
			<li>
				<a href="<?php 
    echo $pager->get_first();
    ?>" aria-label="<?php 
    echo lang('Pager.first');
    ?>">
					<span aria-hidden="true"><?php 
    echo lang('Pager.first');
    ?></span>
				</a>
			</li>
			<li>
				<a href="<?php 
    echo $pager->get_previous();
    ?>" aria-label="<?php 
    echo lang('Pager.previous');
    ?>">
					<span aria-hidden="true"><?php 
    echo lang('Pager.previous');
    ?></span>
				</a>
			</li>
		<?php 
}
?>

		<?php 
foreach ($pager->links() as $link) {
    ?>
			<li <?php 
    echo $link['active'] ? 'class="active"' : '';
    ?>>
				<a href="<?php 
    echo $link['uri'];
    ?>">
					<?php 
    echo $link['title'];
    ?>
				</a>
			</li>
		<?php 
}
?>

		<?php 
if ($pager->has_next()) {
    ?>
			<li>
				<a href="<?php 
    echo $pager->get_next();
    ?>" aria-label="<?php 
    echo lang('Pager.next');
    ?>">
					<span aria-hidden="true"><?php 
    echo lang('Pager.next');
    ?></span>
				</a>
			</li>
			<li>
				<a href="<?php 
    echo $pager->get_last();
    ?>" aria-label="<?php 
    echo lang('Pager.last');
    ?>">
					<span aria-hidden="true"><?php 
    echo lang('Pager.last');
    ?></span>
				</a>
			</li>
		<?php 
}
?>
	</ul>
</nav>
