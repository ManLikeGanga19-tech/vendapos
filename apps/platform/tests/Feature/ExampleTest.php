<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_central_domain_root_sends_people_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
