<?php

declare(strict_types=1);

// Make sure no filters run for our static pages
$this->assertNotHasFilters('about/contact', 'before');
