<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_drive_spa(): void
    {
        $this->get('/')->assertRedirect('/drive');
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_the_health_route_works(): void
    {
        $this->get('/up')->assertOk();
    }
}
