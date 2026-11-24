<?php
namespace Tests\Feature;
use Tests\TestCase;
class ExampleTest extends TestCase
{
    public function test_home_redirects_to_vue(): void
    {
        $this->get('/')->assertRedirect('/app');
        $this->get('/admin-connected.html')->assertRedirect('/app/quan-tri');
    }
}
