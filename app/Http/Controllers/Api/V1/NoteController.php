<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ){}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $note = $this->noteService->getAll();

        return response()->json(
            NoteResource::collection($note)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $this->noteService->create($request->validated());

        return response()->json(
            new NoteResource($note),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note): JsonResponse
    {
        return response()->json(
            new NoteResource($note)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $note = $this->noteService->update($note, $request->validated());

        return response()->json(
            new NoteResource($note)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note): JsonResponse
    {
        $this->noteService->delete($note);

        return response()->json(null, 204);
    }
}
