<?php

declare(strict_types=1);

$this->load->library('pagination');
$config['base_url']    = base_url().'users/index/';
$config['total_rows']  = $this->db->count_all('users');
$config['per_page']    = 10;
$config['uri_segment'] = 3;
$config['attributes']  = ['class' => 'pagination-link'];
$this->pagination->initialize($config);

$data['users'] = $this->user_model->get_users(false, $config['per_page'], $offset);

$this->load->view('posts/index', $data);
