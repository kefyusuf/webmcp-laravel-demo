<div class="hl-wrap" wire:poll.2s>
    {{-- Exposes this component's WebMCP tool schemas to the page bridge. --}}
    @webmcpTools($this)

    <header class="hl-header">
        <div>
            <h1>Shared Prep List</h1>
            <p class="hl-sub">
                A tiny Livewire app where people and AI agents update the same list through
                <code>webmcp-laravel</code>.
            </p>
        </div>
        <div class="hl-status" id="hlStatus" wire:ignore>
            <span class="hl-dot"></span>
            <span>Checking WebMCP...</span>
        </div>
    </header>

    <div class="hl-layout">
        <div>
            <form wire:submit.prevent="humanAdd" class="hl-add-row">
                <input type="text" wire:model="newItemName" placeholder="Add something, e.g. passport" autocomplete="off">
                <button type="submit">Add</button>
            </form>

            <p class="hl-panel-title">LIST</p>
            <ul class="hl-items">
                @forelse ($items as $item)
                    <li class="hl-item">
                        <div class="hl-check {{ $item['done'] ? 'done' : '' }}"
                             wire:click="humanToggle('{{ $item['id'] }}')"></div>
                        <div class="hl-item-body">
                            <div class="hl-item-name {{ $item['done'] ? 'done' : '' }}">{{ $item['name'] }}</div>
                            @if (!empty($item['note']))
                                <div class="hl-item-note">{{ $item['note'] }}</div>
                            @endif
                        </div>
                        <span class="hl-actor-tag {{ $item['actor'] }}">
                            {{ $item['actor_name'] ?? ($item['actor'] === 'agent' ? 'Agent' : 'Guest') }}
                        </span>
                        <button class="hl-remove" wire:click="humanRemove('{{ $item['id'] }}')">Remove</button>
                    </li>
                @empty
                    <p class="hl-empty">The list is empty. Add an item above or ask an agent to add one.</p>
                @endforelse
            </ul>
        </div>

        <div>
            <p class="hl-panel-title">ACTIVITY LOG</p>
            <div class="hl-log">
                @forelse ($log as $entry)
                    <div class="hl-log-entry">
                        <span class="hl-who {{ $entry['actor'] }}">
                            {{ $entry['actor_name'] ?? ($entry['actor'] === 'agent' ? 'Agent' : 'Guest') }}
                        </span>
                        {{ $entry['text'] }}
                        <span class="hl-time">{{ $entry['time'] }}</span>
                    </div>
                @empty
                    <div class="hl-log-entry">No activity yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modelContext = document.modelContext || navigator.modelContext || null;
            const dot = document.querySelector('#hlStatus .hl-dot');
            const label = document.querySelector('#hlStatus span:last-child');
            if (modelContext) {
                dot.classList.add('on');
                label.textContent = 'WebMCP supported - agent ready';
            } else {
                label.textContent = 'WebMCP is not available in this browser';
            }
        })();
    </script>
</div>
