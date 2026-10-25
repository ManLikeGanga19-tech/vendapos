<?php

it('squares every corner in the admin panel', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('*, *::before, *::after { border-radius: 0 !important; }', false);
});
