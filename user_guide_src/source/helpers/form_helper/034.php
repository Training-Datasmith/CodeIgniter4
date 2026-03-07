<?php

declare(strict_types=1);

$string = '</div></div>';
echo form_close($string);
// Would produce:  </form> </div></div>
