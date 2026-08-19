<?php

it('renders the public homepage for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/HomePage', false));
});
