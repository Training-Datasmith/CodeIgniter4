<?php

declare(strict_types=1);

$config['image_library']  = 'gd2';
$config['source_image']   = '/path/to/image/mypic.jpg';
$config['create_thumb']   = true;
$config['maintain_ratio'] = true;
$config['width']          = 75;
$config['height']         = 50;

$this->load->library('image_lib', $config);

$this->image_lib->resize();
