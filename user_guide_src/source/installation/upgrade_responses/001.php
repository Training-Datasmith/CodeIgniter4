<?php

declare(strict_types=1);

$this->response->setStatusCode(404);

// ...

return $this->response->setJSON(['foo' => 'bar']);
