<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farm;
use App\Models\Season;

class SyncController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function initialPackage(Request $request)
    {
        $farmer = $request->user();
        $farms = Farm::where('farmer_id', $farmer->id)->get();
        $seasons = Season::all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'farmer_data' => $farmer,
                'farms' => $farms,
                'seasons' => $seasons,
                'app_settings' => [
                    'notification_frequency' => 'monthly',
                    'data_retention_days' => 90,
                    'version' => '1.0.2'
                ],
                'sync_timestamp' => now()
            ]
        ]);
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
