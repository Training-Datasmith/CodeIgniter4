<?php

declare(strict_types=1);

class Form extends CI_Controller
{
    public function index()
    {
        $this->load->helper(['form', 'url']);

        $this->load->library('form_validation');

        // Set validation rules

        if ($this->form_validation->run() == false) {
            $this->load->view('myform');
        } else {
            $this->load->view('formsuccess');
        }
    }
}
