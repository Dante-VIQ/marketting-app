<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\ChatWidget;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_chat_widget_shows_login_prompt(): void
    {
        Livewire::test(ChatWidget::class)
            ->assertDontSee('Ask me about analytics')
            ->assertSee('Please log in to use Vumbi AI');
    }
}
