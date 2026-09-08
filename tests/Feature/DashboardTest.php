<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_guests_see_the_welcome_page_at_the_root_url()
    {
        $response = $this->get('/');
        $response->assertStatus(200)->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_authenticated_users_are_redirected_from_the_root_url_to_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/');
        $response->assertRedirect('/dashboard');
    }
}
