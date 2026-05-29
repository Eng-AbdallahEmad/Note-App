<?php

namespace App\Services;

use App\Models\Note;
use App\Repositories\NoteRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class NoteService
{
    private const LIST_TTL = 300;
    private const NOTE_TTL = 3600;

    public function __construct(
        private readonly NoteRepository $repo
    ){}

    public function getAll(): LengthAwarePaginator
    {
        $page    = (int) request()->get('page', 1);
        $version = (int) Cache::get('notes.list.version', 0);

        return Cache::remember("notes.list.v{$version}.page.{$page}", self::LIST_TTL, fn() =>
            $this->repo->getAll()
        );
    }

    public function getById(int $id): Note
    {
        return Cache::remember("notes.{$id}", self::NOTE_TTL, fn() =>
            $this->repo->findById($id)
        );
    }

    public function create(array $data): Note
    {
        $note = $this->repo->create($data);
        $this->bustListCache();
        return $note;
    }

    public function update(Note $note, array $data): Note
    {
        $updated = $this->repo->update($note, $data);
        Cache::forget("notes.{$note->id}");
        $this->bustListCache();
        return $updated;
    }

    public function delete(Note $note): void
    {
        Cache::forget("notes.{$note->id}");
        $this->repo->delete($note);
        $this->bustListCache();
    }

    private function bustListCache(): void
    {
        Cache::put('notes.list.version', (int) Cache::get('notes.list.version', 0) + 1);
    }
}
