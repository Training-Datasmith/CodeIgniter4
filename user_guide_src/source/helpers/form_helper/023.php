<?php

declare(strict_types=1);

$string = '</div></div>';
echo form_fieldset_close($string);
// Would produce: </fieldset></div></div>
