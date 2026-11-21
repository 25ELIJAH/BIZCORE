<?php

namespace App\Livewire;

use App\Livewire\Forms\PartyForm;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Shared list + create/edit modal for customers and suppliers.
 * Subclasses only say which model, permission prefix and labels to use.
 */
#[Layout('layouts.app')]
abstract class PartyIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $view = 'active'; // active | inactive | archived

    public PartyForm $form;

    public bool $showForm = false;

    public string $notice = '';

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Permission prefix: "customers" => customers.view / customers.manage. */
    abstract protected function permission(): string;

    /** Singular and plural display names, e.g. ['customer', 'customers']. */
    abstract protected function labels(): array;

    /** Route name of the profile page, or null when there is none yet. */
    protected function profileRoute(): ?string
    {
        return null;
    }

    public function mount(): void
    {
        $this->authorize($this->permission().'.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'view'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize($this->permission().'.manage');
        $this->form->reset();
        $this->form->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize($this->permission().'.manage');
        $this->form->resetValidation();
        $this->form->fillFrom($this->model()::findOrFail($id));
        $this->showForm = true;
    }

    public function save(ActivityLogger $log): void
    {
        $this->authorize($this->permission().'.manage');
        $this->form->validate();
        [$singular] = $this->labels();

        $record = $this->form->id ? $this->model()::findOrFail($this->form->id) : new ($this->model());
        $record->fill($this->form->attributes());
        $changes = $record->getDirty();
        $record->save();
        $log->log($this->form->id ? "{$singular}.updated" : "{$singular}.created", $record, ['changes' => $changes]);

        $this->notice = ucfirst($singular).($this->form->id ? ' updated.' : ' created.');
        $this->closeModal();
    }

    public function archive(int $id, ActivityLogger $log): void
    {
        $this->authorize($this->permission().'.manage');
        $record = $this->model()::findOrFail($id);
        $record->delete();
        $log->log("{$this->labels()[0]}.archived", $record);
        $this->notice = ucfirst($this->labels()[0]).' archived. Their history is kept.';
    }

    public function restore(int $id, ActivityLogger $log): void
    {
        $this->authorize($this->permission().'.manage');
        $record = $this->model()::onlyTrashed()->findOrFail($id);
        $record->restore();
        $log->log("{$this->labels()[0]}.restored", $record);
        $this->notice = ucfirst($this->labels()[0]).' restored.';
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $query = $this->model()::query();

        match ($this->view) {
            'archived' => $query->onlyTrashed(),
            'inactive' => $query->where('is_active', false),
            default => $query->where('is_active', true),
        };

        $records = $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)->orWhere('contact_person', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.party-index', [
            'records' => $records,
            'singular' => $this->labels()[0],
            'plural' => $this->labels()[1],
            'permission' => $this->permission(),
            'profileRoute' => $this->profileRoute(),
        ])->title(ucfirst($this->labels()[1]));
    }
}
