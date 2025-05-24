<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.id' => 'required|string',
            'notes.*.farm_id' => 'required|exists:farms,id',
            'notes.*.farmer_id' => 'required|exists:farmers,id',
            'notes.*.title' => 'required|string',
            'notes.*.content' => 'required|string',
            'notes.*.created_at' => 'required|date',
            'notes.*.updated_at' => 'required|date',
            'notes.*.is_deleted' => 'required|boolean'
        ]);

        foreach ($validated['notes'] as $noteData) {
            Note::updateOrCreate(
                ['id' => $noteData['id']],
                $noteData
            );
        }

        return response()->json(['status' => 'success', 'message' => 'Notes synchronized successfully', 'data' => ['sync_timestamp' => now()]]);
    }

    public function index(Request $request)
    {
        return Note::where('farmer_id', $request->user()->id)->get();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
