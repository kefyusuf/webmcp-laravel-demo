<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Webmcp\Laravel\Attributes\WebMcpTool;
use Webmcp\Laravel\Concerns\HasWebMcpTools;

class PrepList extends Component
{
    use HasWebMcpTools;

    private const ITEMS_KEY = 'prep-list:items';

    private const LOG_KEY = 'prep-list:log';

    public array $items = [];

    public array $log = [];

    public string $newItemName = '';

    public function mount(): void
    {
        $this->syncFromStore();
    }

    // ---------------------------------------------------------------
    // Human UI entry points, intentionally not exposed as WebMCP tools.
    // ---------------------------------------------------------------

    public function humanAdd(): void
    {
        $this->syncFromStore();

        if (trim($this->newItemName) === '') {
            return;
        }

        $this->addItemAs($this->newItemName, null, 'human');
        $this->newItemName = '';
    }

    public function humanToggle(string $id): void
    {
        $this->syncFromStore();
        $this->toggleItemAs($id, 'human');
    }

    public function humanRemove(string $id): void
    {
        $this->syncFromStore();
        $this->removeItemAs($id, 'human');
    }

    // ---------------------------------------------------------------
    // Agent entry points exposed through WebMCP.
    // ---------------------------------------------------------------

    #[WebMcpTool(
        description: 'Adds a new item to the shared preparation list, optionally with a short note.',
        parameters: [
            'name' => ['type' => 'string', 'description' => 'Item name, for example "passport"'],
            'note' => ['type' => 'string', 'description' => 'Optional short note'],
        ],
        required: ['name'],
    )]
    public function addItem(string $name, ?string $note = null): string
    {
        $this->syncFromStore();
        $this->addItemAs($name, $note, 'agent');

        return "\"{$name}\" was added to the list.";
    }

    #[WebMcpTool(
        description: 'Marks the item matching the given name as completed.',
        parameters: ['name' => ['type' => 'string']],
        required: ['name'],
    )]
    public function completeItem(string $name): string
    {
        $this->syncFromStore();
        $item = $this->findByName($name);

        if (! $item) {
            return "\"{$name}\" was not found.";
        }

        $this->toggleItemAs($item['id'], 'agent');

        return "\"{$name}\" was marked as completed.";
    }

    #[WebMcpTool(
        description: 'Removes the item matching the given name from the list.',
        parameters: ['name' => ['type' => 'string']],
        required: ['name'],
    )]
    public function removeItem(string $name): string
    {
        $this->syncFromStore();
        $item = $this->findByName($name);

        if (! $item) {
            return "\"{$name}\" was not found.";
        }

        $this->removeItemAs($item['id'], 'agent');

        return "\"{$name}\" was removed.";
    }

    #[WebMcpTool(
        description: 'Adds or updates a short note on an existing item.',
        parameters: [
            'name' => ['type' => 'string'],
            'note' => ['type' => 'string'],
        ],
        required: ['name', 'note'],
    )]
    public function addNoteToItem(string $name, string $note): string
    {
        $this->syncFromStore();
        $item = $this->findByName($name);

        if (! $item) {
            return "\"{$name}\" was not found.";
        }

        $this->items = collect($this->items)
            ->map(fn ($i) => $i['id'] === $item['id'] ? [...$i, 'note' => $note] : $i)
            ->all();

        $this->pushLog('agent', "added a note to \"{$name}\": \"{$note}\"");
        $this->persist();

        return 'Note added.';
    }

    #[WebMcpTool(description: 'Returns every item in the shared list with status, notes, and author.')]
    public function listItems(): string
    {
        if ($this->items === []) {
            return 'The list is currently empty.';
        }

        return collect($this->items)
            ->map(fn ($i) => sprintf(
                '- %s [%s]%s (added by %s)',
                $i['name'],
                $i['done'] ? 'done' : 'pending',
                $i['note'] ? " - note: {$i['note']}" : '',
                $i['actor_name'] ?? ($i['actor'] === 'agent' ? 'Agent' : 'Guest'),
            ))
            ->implode("\n");
    }

    // ---------------------------------------------------------------
    // Shared behavior: humans and agents use the same state transitions.
    // ---------------------------------------------------------------

    private function addItemAs(string $name, ?string $note, string $actor): void
    {
        $this->items[] = [
            'id' => (string) str()->uuid(),
            'name' => $name,
            'note' => $note,
            'done' => false,
            'actor' => $actor,
            'actor_name' => $this->actorName($actor),
        ];

        $this->pushLog($actor, "added \"{$name}\"");
        $this->persist();
    }

    private function toggleItemAs(string $id, string $actor): void
    {
        $changed = null;

        $this->items = collect($this->items)->map(function ($i) use ($id, &$changed) {
            if ($i['id'] === $id) {
                $i['done'] = ! $i['done'];
                $changed = $i;
            }

            return $i;
        })->all();

        if ($changed) {
            $this->pushLog($actor, ($changed['done'] ? 'completed' : 'reopened')." \"{$changed['name']}\"");
            $this->persist();
        }
    }

    private function removeItemAs(string $id, string $actor): void
    {
        $item = collect($this->items)->firstWhere('id', $id);
        $this->items = collect($this->items)->reject(fn ($i) => $i['id'] === $id)->values()->all();

        if ($item) {
            $this->pushLog($actor, "removed \"{$item['name']}\"");
            $this->persist();
        }
    }

    private function findByName(string $name): ?array
    {
        return collect($this->items)->first(
            fn ($i) => strcasecmp($i['name'], $name) === 0
        );
    }

    private function pushLog(string $actor, string $text): void
    {
        array_unshift($this->log, [
            'actor' => $actor,
            'actor_name' => $this->actorName($actor),
            'text' => $text,
            'time' => now()->format('H:i'),
        ]);
        $this->log = array_slice($this->log, 0, 40);
    }

    private function persist(): void
    {
        Cache::store('file')->put(self::ITEMS_KEY, $this->items);
        Cache::store('file')->put(self::LOG_KEY, $this->log);
    }

    private function syncFromStore(): void
    {
        $this->items = Cache::store('file')->get(self::ITEMS_KEY, []);
        $this->log = Cache::store('file')->get(self::LOG_KEY, []);
    }

    private function actorName(string $actor): string
    {
        $key = "prep-list-{$actor}-name";

        if (! session()->has($key)) {
            session([$key => ($actor === 'agent' ? 'Agent' : 'Guest').' '.random_int(1000, 9999)]);
        }

        return session($key);
    }

    public function render()
    {
        $this->syncFromStore();

        return view('livewire.prep-list');
    }
}
