<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Services\Bot\BotService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ChatWidget extends Component
{
    // UI state
    public bool   $isOpen    = false;
    public bool   $isLoading = false;

    // Chat state
    public string $input     = '';
    public array  $messages  = [];
    public ?int   $sessionId = null;

    // Brand context
    public ?int   $brandId   = null;

    public function mount(?Brand $brand = null): void
    {
        $this->brandId = $brand?->id ?? Auth::user()?->active_brand_id;

        // Greet
        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $this->getGreeting(),
        ];
    }

    public function toggleOpen(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function send(): void
    {
        $message = trim($this->input);
        if (empty($message) || $this->isLoading) {
            return;
        }

        // Append user message immediately
        $this->messages[] = ['role' => 'user', 'content' => $message];
        $this->input = '';
        $this->isLoading = true;

        // Dispatch to self after render so the user message shows first
        $this->dispatch('send-to-ai', message: $message);
    }

    /**
     * This listener actually calls the AI. Dispatched after the user message
     * is already rendered, giving the impression of real-time response.
     */
    #[\Livewire\Attributes\On('send-to-ai')]
    public function processMessage(string $message): void
    {
        try {
            $user  = Auth::user();
            $brand = $this->brandId ? Brand::find($this->brandId) : $user->brands()->first();

            /** @var BotService $bot */
            $bot = app(BotService::class);

            if ($brand) {
                $result = $bot->chat($message, $brand, $user);
            } else {
                $result = $bot->onboardingChat($message, $user);
            }

            $this->sessionId = $result['session_id'];
            $this->messages[] = ['role' => 'assistant', 'content' => $result['reply']];

        } catch (\Throwable $e) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => "Something went wrong on my end. Please try again.",
            ];
        } finally {
            $this->isLoading = false;
        }
    }

    public function clearChat(): void
    {
        try {
            $user  = Auth::user();
            $brand = $this->brandId ? Brand::find($this->brandId) : $user->brands()->first();

            if ($brand) {
                \App\Models\ChatSession::where('brand_id', $brand->id)
                    ->where('user_id', $user->id)
                    ->where('mode', 'assistant')
                    ->delete();
            }
        } catch (\Throwable) {
            // Silent
        }

        $this->messages = [
            ['role' => 'assistant', 'content' => $this->getGreeting()],
        ];
        $this->sessionId = null;
    }

    private function getGreeting(): string
    {
        $user  = Auth::user();
        $brand = $this->brandId ? Brand::find($this->brandId) : $user?->brands()->first();
        $name  = $user?->name ? explode(' ', $user->name)[0] : 'there';

        if (!$brand) {
            return "Hi {$name}! I'm Vumbi AI. Let's get your brand set up. What's your brand called?";
        }

        return "Hi {$name}! I'm your Vumbi AI assistant for **{$brand->name}**. Ask me about analytics, leads, visibility, or what to do next.";
    }

    public function render()
    {
        return view('livewire.chat-widget');
    }
}
