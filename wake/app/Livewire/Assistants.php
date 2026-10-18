<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Wake\Accounts\Tokens;
use Wake\Mcp\CurrentToken;

/**
 * Personal tokens for AI assistants, and how to connect them.
 */
class Assistants extends Component
{
    public string $name = '';

    /** @var list<string> */
    public array $scopes = ['stats:read', 'content:read'];

    /** Days the new token is valid; "never" for no end. */
    public string $days = '90';

    /** Shown once after creating a token. */
    public ?string $plain = null;

    public function create(Tokens $tokens): void
    {
        $this->validate([
            'name' => 'required|string|max:60',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'in:'.implode(',', $this->allowedScopes()),
            'days' => 'required|in:30,90,365,never',
        ]);

        [$this->plain] = $tokens->create(auth()->user(), $this->name, $this->scopes, $this->days === 'never' ? null : (int) $this->days);
        $this->name = '';
    }

    public function revoke(Tokens $tokens, string $id): void
    {
        $tokens->revoke(auth()->user(), $id);
        $this->plain = null;
    }

    /**
     * Nobody can give an assistant more rights than they have themselves.
     *
     * @return list<string>
     */
    public function allowedScopes(): array
    {

        return array_values(array_filter(
            array_keys(CurrentToken::CAPABILITIES),
            fn (string $scope) => collect(CurrentToken::CAPABILITIES[$scope])->contains(fn (string $capability) => auth()->user()->can($capability)),
        ));
    }

    public function render(Tokens $tokens): View
    {
        return view('wake::livewire.assistants', [
            'tokens' => $tokens->of(auth()->user()),
            'allowed' => $this->allowedScopes(),
            'endpoint' => route('wake.mcp'),
        ]);
    }
}
