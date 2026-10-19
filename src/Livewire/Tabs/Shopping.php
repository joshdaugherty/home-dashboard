<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\Groceries;

class Shopping extends Tab
{
    /** What is being typed in the add box. */
    public string $query = '';

    /** The outcome of the last add, for a line under the box. */
    public ?string $notice = null;

    /** Tap an item to take it off the list. */
    public function tick(string $id, Groceries $groceries): void
    {
        $groceries->tick($id);
    }

    /** The item whose quantity is being set. */
    public ?string $editing = null;

    public function edit(string $id): void
    {
        $this->editing = $id;
    }

    public function close(): void
    {
        $this->editing = null;
    }

    public function setQuantity(string $id, int $qty, Groceries $groceries): void
    {
        $groceries->setQuantity($id, $qty);
    }

    /** Add what was typed: a known item goes back on the list, anything else becomes a new item. */
    public function add(Groceries $groceries): void
    {
        $name = $groceries->addByName($this->query);

        $this->notice = $name ? "Added {$name}." : 'That could not be added right now.';

        if ($name) {
            $this->query = '';
        }
    }

    /** Tap a suggestion to put a known item on the list. */
    public function addItem(string $id, Groceries $groceries): void
    {
        $this->notice = $groceries->need($id) ? 'Added.' : 'That could not be added right now.';
        $this->query = '';
    }

    public function render(Groceries $groceries)
    {
        return view('home-dashboard::livewire.tabs.shopping', [
            'refresh' => $this->refreshSeconds(),
            'configured' => $groceries->configured(),
            'groups' => $groceries->shoppingList(),
            'suggestions' => $groceries->suggestions($this->query),
        ]);
    }
}
