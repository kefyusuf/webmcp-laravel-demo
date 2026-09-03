<?php

namespace Tests\Feature;

use App\Livewire\PrepList;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class PrepListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('file')->forget('prep-list:items');
        Cache::store('file')->forget('prep-list:log');
    }

    public function test_agent_and_human_share_the_file_cached_list_without_database(): void
    {
        session(['prep-list-agent-name' => 'Agent 1234']);

        Livewire::test(PrepList::class)
            ->call('addItem', 'passport', 'required for border crossing');

        session(['prep-list-human-name' => 'Guest 5678']);

        Livewire::test(PrepList::class)
            ->set('newItemName', 'charger')
            ->call('humanAdd');

        $items = Cache::store('file')->get('prep-list:items');
        $log = Cache::store('file')->get('prep-list:log');

        $this->assertCount(2, $items);
        $this->assertSame('passport', $items[0]['name']);
        $this->assertSame('Agent 1234', $items[0]['actor_name']);
        $this->assertSame('charger', $items[1]['name']);
        $this->assertSame('Guest 5678', $items[1]['actor_name']);
        $this->assertSame('Guest 5678', $log[0]['actor_name']);

        Livewire::test(PrepList::class)
            ->assertSee('passport')
            ->assertSee('Agent 1234')
            ->assertSee('charger')
            ->assertSee('Guest 5678');
    }

    public function test_prep_list_view_polls_for_shared_updates(): void
    {
        $this->get('/prep-list')
            ->assertOk()
            ->assertSee('wire:poll.2s', false)
            ->assertSee('wire:ignore', false);
    }
}
