<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farm;

class FarmController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Farm::all();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'size' => 'required|numeric',
            'district' => 'required|string',
            'village' => 'required|string',
            'farmer_id' => 'required|exists:farmers,id',
            'planting_date' => 'required|date',
            'current_season_month' => 'required|integer|between:1,12'
        ]);

        $farm = Farm::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Farm added successfully', 'data' => $farm], 201);
    }
    

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Farm::findOrFail($id);
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
        $validated = $request->validate([
            'name' => 'required|string',
            'size' => 'required|numeric',
            'district' => 'required|string',
            'village' => 'required|string',
            'planting_date' => 'required|date',
            'current_season_month' => 'required|integer|between:1,12'
        ]);

        $farm = Farm::findOrFail($id);
        $farm->update($validated);

        return response()->json(['status' => 'success', 'message' => 'Farm updated successfully', 'data' => $farm]);
    
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Farm::destroy($id);
        return response()->json(['status' => 'success', 'message' => 'Farm deleted successfully']);
    }
}
