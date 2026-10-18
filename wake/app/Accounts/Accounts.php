<?php

namespace Wake\Accounts;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;

/**
 * Reads and writes team accounts. Each account is a folder in
 * storage/accounts/ with an account.yml file. API tokens and other personal
 * data live in the same folder.
 */
class Accounts
{
    /** @var array<string, Account>|null */
    protected ?array $cache = null;

    /**
     * @return Collection<string, Account>
     */
    public function all(): Collection
    {
        if ($this->cache === null) {
            $this->cache = [];

            foreach (glob($this->directory().'/*/account.yml') ?: [] as $file) {
                $attributes = Yaml::parseFile($file) ?: [];

                if (isset($attributes['id'])) {
                    $this->cache[$attributes['id']] = new Account($attributes);
                }
            }

            ksort($this->cache);
        }

        return collect($this->cache);
    }

    public function exists(): bool
    {
        return $this->all()->isNotEmpty();
    }

    public function find(?string $id): ?Account
    {
        return $id === null ? null : $this->all()->get($id);
    }

    public function findByEmail(string $email): ?Account
    {
        $email = mb_strtolower(trim($email));

        return $this->all()->first(fn (Account $account) => mb_strtolower($account->email()) === $email);
    }

    public function owner(): ?Account
    {
        return $this->all()->first(fn (Account $account) => $account->role()->isOwner());
    }

    /**
     * @param  array{name: string, email: string, password?: string, role?: Role|string, language?: string}  $data
     */
    public function create(array $data): Account
    {
        if ($this->findByEmail($data['email'])) {
            throw new InvalidArgumentException('An account with this email address exists already.');
        }

        $id = $this->uniqueId(Str::slug($data['name']) ?: 'member');
        $role = $data['role'] ?? Role::Contributor;
        $role = $role instanceof Role ? $role->key : (string) $role;

        $account = new Account(array_filter([
            'id' => $id,
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'role' => $role,
            'language' => $data['language'] ?? null,
            'created_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null));

        $this->save($account);

        return $account;
    }

    public function save(Account $account): void
    {
        Files::lock('account-'.$account->id(), function () use ($account) {
            Files::write($this->path($account->id()), Yaml::dump($account->toArray(), 2, 2));
        });

        $this->cache = null;
    }

    public function delete(Account $account): void
    {
        Files::deleteDirectory($this->directory().'/'.$account->id());
        $this->cache = null;
    }

    /**
     * Move an account with its tokens to storage/trash, like content.
     */
    public function trash(Account $account): void
    {
        $target = storage_path('trash/'.now()->format('Y-m-d-His').'-account--'.$account->id());
        @mkdir(dirname($target), 0775, true);
        rename($this->folder($account->id()), $target);
        $this->cache = null;
    }

    public function directory(): string
    {
        return storage_path('accounts');
    }

    public function folder(string $id): string
    {
        return $this->directory().'/'.$id;
    }

    protected function path(string $id): string
    {
        return $this->folder($id).'/account.yml';
    }

    protected function uniqueId(string $base): string
    {
        $id = $base;

        for ($i = 2; is_file($this->path($id)); $i++) {
            $id = $base.'-'.$i;
        }

        return $id;
    }
}
