<?php

declare(strict_types=1);

$onlyInactive = service('request')->getPost('return_inactive');

$users = $this->db->table('users')
    ->when($onlyInactive, static function ($query, $onlyInactive) {
        $query->where('status', 'inactive');
    }, static function ($query) {
        $query->where('status', 'active');
    })
    ->get();
