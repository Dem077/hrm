<?php

test('login page is accessible to guests', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('dashboard requires authentication', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
