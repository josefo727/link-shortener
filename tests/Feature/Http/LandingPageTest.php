<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Landing Page', function (): void {
    it('displays the landing page on index route', function (): void {
        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertViewIs('landing');
    });

    it('contains promotional links', function (): void {
        $response = $this->get('/');

        $response->assertSee('jose-gutierrez.com');
        $response->assertSee('bajolalupa.net');
        $response->assertSee('josefo727@gmail.com');
    });

    it('contains technology and web development section', function (): void {
        $response = $this->get('/');

        $response->assertSee('Tecnolog');
        $response->assertSee('Desarrollo Web');
    });

    it('contains apologetics section', function (): void {
        $response = $this->get('/');

        $response->assertSee('Cristiana');
        $response->assertSee('bajolalupa.net');
    });

    it('contains consulting section', function (): void {
        $response = $this->get('/');

        $response->assertSee('consultor');
    });
});

describe('404 Error Page', function (): void {
    it('displays custom 404 page for non-existent routes', function (): void {
        $response = $this->get('/non-existent-route-xyz123');

        $response->assertStatus(404);
    });

    it('shows resource not found message', function (): void {
        $response = $this->get('/non-existent-route-xyz123');

        $response->assertSee('No ha sido posible encontrar');
    });

    it('contains promotional links on 404 page', function (): void {
        $response = $this->get('/non-existent-route-xyz123');

        $response->assertSee('jose-gutierrez.com');
        $response->assertSee('bajolalupa.net');
        $response->assertSee('josefo727@gmail.com');
    });
});
