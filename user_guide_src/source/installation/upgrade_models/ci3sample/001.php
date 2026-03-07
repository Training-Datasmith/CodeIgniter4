<?php

declare(strict_types=1);

class News_model extends CI_Model
{
    public function set_news($title, $slug, $text)
    {
        $data = [
            'title' => $title,
            'slug'  => $slug,
            'text'  => $text,
        ];

        return $this->db->insert('news', $data);
    }
}
