<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\Tokens;
use Wake\Content\Categories;
use Wake\Content\Category;
use Wake\Content\Writer;
use Wake\Livewire\PluginManager;
use Wake\Markdown\Markdown;
use Wake\Plugins\Plugins;
use Wake\Support\Files;
use Wake\Support\Paths;

it('finds plugins and reads their plugin.yml', function () {
    $info = app(Plugins::class)->get('greeter');

    expect($info->name())->toBe('Greeter')
        ->and($info->version())->toBe('1.0.0')
        ->and($info->options())->toHaveKey('greeting')
        ->and(app(Plugins::class)->isRunning('greeter'))->toBeTrue();
});

it('adds cards to Markdown', function () {
    expect((string) app(Markdown::class)->render("::: greeting Hi name=Ada\n:::"))->toContain('<p class="greeting">Hi, Ada!</p>');
});

it('adds website addresses before the catch-all route', function () {
    $this->get('/hello-plugin')->assertOk()->assertSee('Hello from a plugin');
});

it('adds to the head of every page', function () {
    $this->get('/')->assertOk()->assertSee('<meta name="greeter" content="Hello">', false);
});

it('adds field types for categories', function () {
    expect(Category::fieldTypes())->toContain('colour');

    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    app(Categories::class)->save('things', ['title' => 'Things', 'fields' => ['tint' => ['type' => 'colour', 'label' => 'Tint']]]);
    $post = app(Writer::class)->create('post', ['title' => 'A thing', 'category' => 'things']);

    $this->actingAs($owner)->get(route('wake.entries.edit', ['type' => 'post', 'id' => $post->id()]))
        ->assertOk()->assertSee('type="color"', false);
});

it('skips a plugin that fails to start and shows why', function () {
    Files::write(Paths::plugins('broken/plugin.php'), "<?php\nreturn function (\$plugin) { throw new RuntimeException('Something is missing'); };\n");
    Files::write(Paths::plugins('syntax/plugin.php'), "<?php\nreturn function ( {\n");

    $plugins = new Plugins;
    $plugins->boot();

    expect($plugins->isRunning('broken'))->toBeFalse()
        ->and($plugins->error('broken'))->toContain('Something is missing')
        ->and($plugins->error('syntax'))->not->toBeNull()
        ->and($plugins->isRunning('greeter'))->toBeTrue();
});

it('switches plugins off and saves their settings in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    $this->actingAs($owner)->get('/wake/plugins')->assertOk()->assertSee('Greeter');

    Livewire::actingAs($owner)->test(PluginManager::class)
        ->set('options.greeter.greeting', 'Moin')
        ->call('saveOptions', 'greeter');

    expect(config('wake.plugins.options.greeter.greeting'))->toBe('Moin');

    Livewire::actingAs($owner)->test(PluginManager::class)->call('toggle', 'greeter');

    expect(config('wake.plugins.disabled'))->toBe(['greeter'])
        ->and(app(Plugins::class)->isEnabled('greeter'))->toBeFalse();

    $fresh = new Plugins;
    $fresh->boot();
    expect($fresh->isRunning('greeter'))->toBeFalse();
});

it('keeps plugins away from editors', function () {
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($editor)->get('/wake/plugins')->assertForbidden();
});

it('loads plugin classes from the folders in "autoload", never from outside the plugin', function () {
    $info = app(Plugins::class)->get('greeter');

    expect(array_keys($info->autoload()))->toBe(['Greeter\\'])
        ->and(class_exists('Greeter\\Text\\Salutation'))->toBeTrue();
});

it('adds pages to the panel menu for people who may see them', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($owner)->get('/wake/plugin/greeter/desk')->assertOk()
        ->assertSee('Greeter desk')->assertSee('Count 0')
        ->assertSee('href="'.route('wake.plugin.greeter.desk').'"', false);

    $this->actingAs($editor)->get('/wake')->assertDontSee(route('wake.plugin.greeter.desk'));
    $this->actingAs($editor)->get('/wake/plugin/greeter/desk')->assertForbidden();
});

it('registers Livewire components under the plugin name', function () {
    Livewire::test('greeter.counter')->call('add')->assertSee('Count 1');
});

it('adds tools to the MCP server', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    [$token] = app(Tokens::class)->create($owner, 'Test', ['content:read']);
    $call = fn (array $body) => $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json, text/event-stream'])
        ->postJson('/wake/mcp', ['jsonrpc' => '2.0', 'id' => 1, ...$body]);

    expect(collect($call(['method' => 'tools/list'])->json('result.tools'))->pluck('name'))->toContain('greeter_greet', 'site_info');

    $call(['method' => 'tools/call', 'params' => ['name' => 'greeter_greet', 'arguments' => []]])
        ->assertOk()->assertSee('Hello from a plugin tool');
});
