<?php

namespace App\Repositories;

use App\Models\Note;
use Illuminate\Pagination\LengthAwarePaginator;

class NoteRepository
{
    public function getAll(): LengthAwarePaginator
    {
        return Note::orderByDesc('is_pinned')
                    ->orderByDesc('created_at')
                    ->paginate(15);
    }

    public function findById(int $id): Note
    {
        return Note::findOrFail($id);
    }

    public function create(array $data): Note
    {
        return Note::create($data);
    }

    public function update(Note $note, array $data): Note
    {
        $note->update($data);
        return $note->refresh();
    }

    public function delete(Note $note): void
    {
        $note->delete();
    }
}
