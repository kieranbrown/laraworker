<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Note::query()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $note = Note::query()->create($validated);

        return response()->json($note, 201);
    }

    public function show(Note $note): JsonResponse
    {
        return response()->json($note);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $note->update($validated);

        return response()->json($note);
    }

    public function destroy(Note $note): JsonResponse
    {
        $note->delete();

        return response()->json(null, 204);
    }
}
