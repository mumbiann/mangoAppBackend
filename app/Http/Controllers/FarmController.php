<?php

// app/Http/Controllers/FarmController.php
namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Season;
use App\Http\Traits\HasAuthenticatedFarmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class FarmController extends Controller
{
    use HasAuthenticatedFarmer;

    /**
     * Unified farm creation endpoint
     * Creates farmer on first farm, links to existing farmer on subsequent farms
     */
    public function store(Request $request)
    {
        // Check if UUID is provided in header
        $uuid = $request->header('X-User-ID');
        $isFirstFarm = empty($uuid);

        Log::info('Farm creation request', [
            'is_first_farm' => $isFirstFarm,
            'uuid' => $uuid ? substr($uuid, 0, 8) . '...' : null,
            'ip' => $request->ip()
        ]);

        // Dynamic validation rules based on whether it's first farm or not
        $rules = $this->getValidationRules($isFirstFarm);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::warning('Farm creation validation failed', [
                'errors' => $validator->errors(),
                'is_first_farm' => $isFirstFarm
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            if ($isFirstFarm) {
                $farmer = $this->createNewFarmer($validated);
                Log::info('New farmer created', [
                    'farmer_id' => $farmer->id,
                    'farmer_uuid' => $farmer->uuid,
                    'farmer_name' => $farmer->name
                ]);
            } else {
                $farmer = Farmer::where('uuid', $uuid)->first();
                
                if (!$farmer) {
                    DB::rollback();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'FARMER_NOT_FOUND',
                        'message' => 'Farmer not found with provided UUID'
                    ], 404);
                }

                Log::info('Existing farmer found', [
                    'farmer_id' => $farmer->id,
                    'farmer_name' => $farmer->name
                ]);
            }

            // Calculate current season month based on planting date
            $currentSeasonMonth = $this->calculateCurrentSeasonMonth($validated['planting_date']);

            // Create farm
            $farm = $this->createFarm($farmer, $validated, $currentSeasonMonth);

            // Get current season details
            $currentSeason = Season::getFullDetails($currentSeasonMonth);

            // Prepare response data
            $responseData = [
                'farm' => $this->formatFarmResponse($farm),
                'current_season' => $this->formatSeasonResponse($currentSeason)
            ];

            // For first farm, include UUID and all seasons summary
            if ($isFirstFarm) {
                $responseData['farmer_uuid'] = $farmer->uuid;
                $responseData['all_seasons_summary'] = Season::getSummary();
            }

            DB::commit();

            Log::info('Farm created successfully', [
                'farm_id' => $farm->id,
                'farmer_id' => $farmer->id,
                'is_first_farm' => $isFirstFarm,
                'current_season_month' => $currentSeasonMonth
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $isFirstFarm ? 'Farmer and farm created successfully' : 'Farm created successfully',
                'data' => $responseData
            ], 201);

        } catch (Exception $e) {
            DB::rollback();
            
            Log::error('Farm creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'is_first_farm' => $isFirstFarm
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => 'CREATION_FAILED',
                'message' => 'Failed to create farm',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get current season instructions for a farm (when month elapses)
     */
    public function getCurrentSeason(Request $request, Farm $farm)
    {
        $farmer = $this->getAuthenticatedFarmer($request);

        // Verify farm ownership
        if ($ownershipError = $this->verifyFarmOwnership($request, $farm)) {
            return $ownershipError;
        }

        // Recalculate current season month in case time has passed
        $expectedSeasonMonth = $this->calculateCurrentSeasonMonth($farm->planting_date);
        
        // Update farm's current season month if it has changed
        if ($farm->current_season_month !== $expectedSeasonMonth) {
            $farm->update(['current_season_month' => $expectedSeasonMonth]);
            
            Log::info('Farm season updated', [
                'farm_id' => $farm->id,
                'old_season' => $farm->current_season_month,
                'new_season' => $expectedSeasonMonth
            ]);
        }

        // Get current season details
        $currentSeason = Season::getFullDetails($expectedSeasonMonth);

        return response()->json([
            'status' => 'success',
            'data' => [
                'farm' => $this->formatFarmResponse($farm->fresh()),
                'current_season' => $this->formatSeasonResponse($currentSeason)
            ]
        ]);
    }

    /**
     * Get all farms for the authenticated farmer
     */
    public function index(Request $request)
    {
        $farmer = $this->getAuthenticatedFarmer($request);
        
        $farms = $this->getFarmerFarmsQuery($farmer)
                    ->with(['notes' => function($query) {
                        $query->latest()->limit(3); // Include latest 3 notes per farm
                    }])
                    ->get()
                    ->map(function($farm) {
                        return $this->formatFarmResponse($farm, true); // Include notes
                    });

        return response()->json([
            'status' => 'success',
            'data' => [
                'farms' => $farms,
                'total_farms' => $farms->count(),
                'total_size' => $farms->sum('size')
            ]
        ]);
    }

    /**
     * Get a specific farm
     */
    public function show(Request $request, Farm $farm)
    {
        if ($ownershipError = $this->verifyFarmOwnership($request, $farm)) {
            return $ownershipError;
        }

        $farm->load(['notes' => function($query) {
            $query->latest()->limit(10); // Load latest 10 notes
        }]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'farm' => $this->formatFarmResponse($farm, true),
                'current_season' => $this->formatSeasonResponse($farm->getCurrentSeason())
            ]
        ]);
    }

    /**
     * Update a farm
     */
    public function update(Request $request, Farm $farm)
    {
        if ($ownershipError = $this->verifyFarmOwnership($request, $farm)) {
            return $ownershipError;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'size' => 'sometimes|required|numeric|min:0|max:999999.99',
            'district' => 'sometimes|required|string|max:255',
            'village' => 'sometimes|required|string|max:255',
            'planting_date' => 'sometimes|required|date|before_or_equal:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Recalculate season month if planting date changed
        if (isset($validated['planting_date'])) {
            $validated['current_season_month'] = $this->calculateCurrentSeasonMonth($validated['planting_date']);
        }

        $farm->update($validated);

        Log::info('Farm updated', [
            'farm_id' => $farm->id,
            'updated_fields' => array_keys($validated)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Farm updated successfully',
            'data' => [
                'farm' => $this->formatFarmResponse($farm->fresh())
            ]
        ]);
    }

    /**
     * Delete a farm
     */
    public function destroy(Request $request, Farm $farm)
    {
        if ($ownershipError = $this->verifyFarmOwnership($request, $farm)) {
            return $ownershipError;
        }

        $farmId = $farm->id;
        $farmName = $farm->name;
        
        $farm->delete();

        Log::info('Farm deleted', [
            'farm_id' => $farmId,
            'farm_name' => $farmName
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Farm deleted successfully'
        ]);
    }

    // ===== PRIVATE HELPER METHODS =====

    /**
     * Get validation rules based on farm type
     */
    private function getValidationRules($isFirstFarm)
    {
        $farmRules = [
            'farm_name' => 'required|string|max:255',
            'farm_size' => 'required|numeric|min:0|max:999999.99',
            'farm_district' => 'required|string|max:255',
            'farm_village' => 'required|string|max:255',
            'planting_date' => 'required|date|before_or_equal:today'
        ];

        if ($isFirstFarm) {
            $farmerRules = [
                'farmer_name' => 'required|string|max:255'
            ];
            return array_merge($farmerRules, $farmRules);
        }

        return $farmRules;
    }

    /**
     * Create a new farmer
     */
    private function createNewFarmer($validated)
    {
        return Farmer::createNew($validated['farmer_name']);
    }

    /**
     * Create a new farm
     */
    private function createFarm($farmer, $validated, $currentSeasonMonth)
    {
        return Farm::create([
            'farmer_id' => $farmer->id,
            'name' => $validated['farm_name'],
            'size' => $validated['farm_size'],
            'district' => $validated['farm_district'],
            'village' => $validated['farm_village'],
            'planting_date' => $validated['planting_date'],
            'current_season_month' => $currentSeasonMonth
        ]);
    }

    /**
     * Calculate the current season month based on planting date
     */
    private function calculateCurrentSeasonMonth($plantingDate): int
    {
        $plantingDate = Carbon::parse($plantingDate);
        $now = Carbon::now();
        
        // Calculate months elapsed since planting
        $monthsElapsed = $plantingDate->diffInMonths($now);
        
        // Use modulo to get current month in 12-month cycle (1-12)
        $currentMonth = ($monthsElapsed % 12) + 1;
        
        return $currentMonth;
    }

    /**
     * Format farm data for API response
     */
    private function formatFarmResponse($farm, $includeNotes = false)
    {
        $farmData = [
            'id' => $farm->id,
            'farmer_id' => $farm->farmer_id,
            'name' => $farm->name,
            'size' => $farm->size,
            'district' => $farm->district,
            'village' => $farm->village,
            'full_location' => $farm->full_location,
            'planting_date' => $farm->planting_date->format('Y-m-d'),
            'current_season_month' => $farm->current_season_month,
            'age_in_months' => $farm->age_in_months,
            'created_at' => $farm->created_at->toISOString(),
            'updated_at' => $farm->updated_at->toISOString()
        ];

        if ($includeNotes && $farm->relationLoaded('notes')) {
            $farmData['recent_notes'] = $farm->notes->map(function($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'excerpt' => $note->excerpt,
                    'created_at' => $note->created_at->toISOString()
                ];
            });
            $farmData['notes_count'] = $farm->notes()->count();
        }

        return $farmData;
    }

    /**
     * Format season data for API response
     */
    private function formatSeasonResponse($season)
    {
        if (!$season) {
            return null;
        }

        return [
            'month' => $season->month,
            'title' => $season->title,
            'short_description' => $season->short_description,
            'full_instructions' => $season->full_instructions,
            'activities' => $season->formatted_activities
        ];
    }
}