<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Season;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class SeasonController extends Controller
{

    public function summary()
    {
        // Cache the seasons summary for 1 hour since it rarely changes
        $seasons = Cache::remember('seasons_summary', 3600, function () {
            return Season::getSummary();
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'seasons_summary' => $seasons
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($month)
    {

        $validator = Validator::make(['month' => $month], [
            'month' => 'required|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid month number. Must be between 1 and 12.'
            ], 422);
        }

        // Cache individual season details for 1 hour
        $season = Cache::remember("season_details_{$month}", 3600, function () use ($month) {
            return Season::getFullDetails($month);
        });

        if (!$season) {
            return response()->json([
                'status' => 'error',
                'code' => 'SEASON_NOT_FOUND',
                'message' => 'Season not found for the specified month'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'season' => [
                    'month' => $season->month,
                    'title' => $season->title,
                    'short_description' => $season->short_description,
                    'full_instructions' => $season->full_instructions,
                    'activities' => $season->formatted_activities,
                    'next_season' => $this->getSeasonSummary($season->getNextSeason()),
                    'previous_season' => $this->getSeasonSummary($season->getPreviousSeason())
                ]
            ]
        ]);
    }

    /**
     * Get all seasons with full details
     * Used for admin or complete season overviews
     */
    public function index()
    {
        // Cache all seasons for 1 hour
        $seasons = Cache::remember('all_seasons_full', 3600, function () {
            return Season::getAllDetails();
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'seasons' => $seasons->map(function($season) {
                    return [
                        'month' => $season->month,
                        'title' => $season->title,
                        'short_description' => $season->short_description,
                        'full_instructions' => $season->full_instructions,
                        'activities' => $season->formatted_activities
                    ];
                }),
                'total_seasons' => $seasons->count()
            ]
        ]);
    }

    /**
     * Helper method to format season summary
     */
    private function getSeasonSummary($season)
    {
        if (!$season) {
            return null;
        }

        return [
            'month' => $season->month,
            'title' => $season->title,
            'short_description' => $season->short_description
        ];
    }
}
