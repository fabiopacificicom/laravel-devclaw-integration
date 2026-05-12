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

    public string $viewMode = 'grid';

    public int $month;
    public int $year;

    public bool $showModal = false;
    public bool $showDeleteConfirm = false;
    public ?int $deleteId = null;

    // Flat form fields
    public ?int $editingId = null;
    public string $title = '';
    public string $type = 'post';
    public string $status = 'draft';
    public string $scheduled_at = '';
    public string $color = '#3b82f6';
    public string $notes = '';

    public array $entries = [];

    protected function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:post,course,workshop,youtube_video,linkedin,other',
            'status'       => 'required|in:draft,scheduled,published,cancelled',
            'scheduled_at' => 'required|date',
            'color'        => 'nullable|string|max:7',
            'notes'        => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
        $this->entries = $this->getEntriesForRange();
    }

    public function navigate(string $direction): void
    {
        if ($direction === 'next') {
            $this->nextMonth();
        } else {
            $this->previousMonth();
        }
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->month = $date->month;
        $this->year = $date->year;
        $this->entries = $this->getEntriesForRange();
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->month = $date->month;
        $this->year = $date->year;
        $this->entries = $this->getEntriesForRange();
    }

    public function goToToday(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
        $this->entries = $this->getEntriesForRange();
    }

    public function goToday(): void
    {
        $this->goToToday();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->entries = $this->getEntriesForRange();
    }

    public function openCreate(string $date = ''): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->type = 'post';
        $this->status = 'draft';
        $this->scheduled_at = $date ?: now()->format('Y-m-d\TH:i');
        $this->color = '#3b82f6';
        $this->notes = '';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $entry = CalendarEntry::findOrFail($id);
        $this->editingId = $entry->id;
        $this->title = $entry->title;
        $this->type = $entry->type;
        $this->status = $entry->status;
        $this->scheduled_at = $entry->scheduled_at->format('Y-m-d\TH:i');
        $this->color = $entry->color;
        $this->notes = $entry->notes ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'        => $this->title,
            'type'         => $this->type,
            'status'       => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'color'        => $this->color ?: '#3b82f6',
            'notes'        => $this->notes ?: null,
        ];

        if ($this->editingId) {
            CalendarEntry::findOrFail($this->editingId)->update($data);
        } else {
            CalendarEntry::create($data);
        }

        $this->showModal = false;
        $this->editingId = null;
        $this->title = '';
        $this->entries = $this->getEntriesForRange();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            CalendarEntry::findOrFail($this->deleteId)->delete();
        }
        $this->deleteId = null;
        $this->showDeleteConfirm = false;
        $this->showModal = false;
        $this->entries = $this->getEntriesForRange();
    }

    public function getEntriesForRange(): array
    {
        $base = Carbon::createFromDate($this->year ?? now()->year, $this->month ?? now()->month, 1);

        [$from, $to] = match ($this->mode) {
            'daily'       => [$base->copy()->startOfDay(), $base->copy()->endOfDay()],
            'weekly'      => [$base->copy()->startOfWeek(), $base->copy()->endOfWeek()],
            'multi-month' => [$base->copy()->startOfMonth(), $base->copy()->addMonths(2)->endOfMonth()],
            default       => [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()],
        };

        return CalendarEntry::inRange($from, $to)
            ->orderBy('scheduled_at')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('pacificdev-devclaw::calendar.content-calendar');
    }
}
