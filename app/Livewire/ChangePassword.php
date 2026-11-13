<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class ChangePassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = Auth::user();
        $user->password = $this->password;
        $user->must_change_password = false;
        $user->save();

        $this->redirect(route('home'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.change-password');
    }
}
