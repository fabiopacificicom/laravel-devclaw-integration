<?php

namespace Pacificdev\DevClaw\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Pacificdev\DevClaw\Models\CalendarEntry;

class ContentCalendar extends Component
{
    #[Url]
    public string $mode = 'monthly';

    #[Url]
    public string $currentDate = '';

    public bool $showModal = false;

    public array $editingEntry = [];

    public array $entries = [];

    protected function rules(): array
    {
        return [
            'editingEntry.title'        => 'required|string|max:255',
            'editingEntry.type'         => 'required|in:post,course,workshop,youtube_video,linkedin,other',
            'editingEntry.status'       => 'required|in:draft,scheduled,published,cancelled',
            'editingEntry.scheduled_at' => 'required|date',
            'editingEntry.color'        => 'nullable|string|max:7',
            'editingEntry.notes'        => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        if (empty($this->currentDate)) {
            $this->currentDate = now()->format('Y-m');
        }
        $this->entries = $this->getEntriesForRange();
    }

    public function navigate(string $direction): void
    {
        $date = Carbon::createFromFormat('Y-m', $this->currentDate);

        match ($this->mode) {
            'daily'       => $direction === 'next' ? $date->addDay()   : $date->subDay(),
            'weekly'      => $direction === 'next' ? $date->addWeek()  : $date->subWeek(),
            'multi-month' => $direction === 'next' ? $date->addMonths(3) : $date->subMonths(3),
            default       => $direction === 'next' ? $date->addMonth() : $date->subMonth(),
        };

        $this->currentDate = $date->format('Y-m');
        $this->entries = $this->getEntriesForRange();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->entries = $this->getEntriesForRange();
    }

    public function goToday(): void
    {
        $this->currentDate = now()->format('Y-m');
        $this->entries = $this->getEntriesForRange();
    }

    public function openCreate(string $date = ''): void
    {
        $this->editingEntry = [
            'id'           => null,
            'title'        => '',
            'type'         => 'post',
            'status'       => 'draft',
            'scheduled_at' => $date ?: now()->format('Y-m-d\TH:i'),
            'color'        => '#3b82f6',
            'notes'        => '',
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $entry = CalendarEntry::findOrFail($id);
        $this->editingEntry = [
            'id'           => $entry->id,
            'title'        => $entry->title,
            'type'         => $entry->type,
            'status'       => $entry->status,
            'scheduled_at' => $entry->scheduled_at->format('Y-m-d\TH:i'),
            'color'        => $entry->color,
            'notes'        => $entry->notes ?? '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'        => $this->editingEntry['title'],
            'type'         => $this->editingEntry['type'],
            'status'       => $this->editingEntry['status'],
            'scheduled_at' => $this->editingEntry['scheduled_at'],
            'color'        => $this->editingEntry['color'] ?? '#3b82f6',
            'notes'        => $this->editingEntry['notes'] ?? null,
        ];

        if (!empty($this->editingEntry['id'])) {
            CalendarEntry::findOrFail($this->editingEntry['id'])->update($data);
        } else {
            CalendarEntry::create($data);
        }

        $this->showModal = false;
        $this->editingEntry = [];
        $this->entries = $this->getEntriesForRange();
    }

    public function delete(int $id): void
    {
        CalendarEntry::findOrFail($id)->delete();
        $this->showModal = false;
        $this->editingEntry = [];
        $this->entries = $this->getEntriesForRange();
    }

    public function getEntriesForRange(): array
    {
        [$from, $to] = $this->resolveRange();
        return CalendarEntry::inRange($from, $to)
            ->orderBy('scheduled_at')
            ->get()
            ->toArray();
    }

    private function resolveRange(): array
    {
        $base = Carbon::createFromFormat('Y-m', $this->currentDate ?: now()->format('Y-m'));

        return match ($this->mode) {
            'daily'       => [$base->copy()->startOfDay(), $base->copy()->endOfDay()],
            'weekly'      => [$base->copy()->startOfWeek(), $base->copy()->endOfWeek()],
            'multi-month' => [$base->copy()->startOfMonth(), $base->copy()->addMonths(2)->endOfMonth()],
            default       => [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()],
        };
    }

    public function render()
    {
        return view('pacificdev-devclaw::calendar.content-calendar');
    }
}
