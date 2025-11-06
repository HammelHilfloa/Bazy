<?php

namespace App\Http\Controllers;

class FamilyController
{
    public function show(): string
    {
        ob_start();
        include __DIR__ . '/../../resources/views/family/home.php';
        return ob_get_clean();
    }
}
