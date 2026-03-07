<?php

declare(strict_types=1);

// Contents of photo.jpg will be automatically read
return $this->response->download('/path/to/photo.jpg', null);
