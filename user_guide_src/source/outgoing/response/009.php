<?php

declare(strict_types=1);

return $this->response->download('awkwardEncryptedFileName.fakeExt', null)->setFileName('expenses.csv');
