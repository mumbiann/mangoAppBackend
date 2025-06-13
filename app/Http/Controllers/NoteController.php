<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Farm;
use App\Http\Traits\HasAuthenticatedFarmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class NoteController extends Controller
{
    use HasAuthenticatedFarmer;

    /**
     * Sync notes from mobile app to backend
     * Called every 1-2 weeks when app is online
     */

     public function sync(Request $request)
    {
        $farmer = $this->getAuthenticatedFarmer($request);

        $validator = Validator::make($request->all(), [
            'notes' => 'required|array|min:1|max:100', // Limit to 100 notes per sync
            'notes.*.farm_id' => 'required|integer|exists:farms,id',
            'notes.*.title' => 'required|string|max:255',
            'notes.*.content' => 'required|string|max:10000', // 10KB max per note
            'notes.*.created_at' => 'required|date',
            'notes.*.updated_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $notesData = $request->input('notes');
        $syncedCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($notesData as $index => $noteData) {
                try {
                    // Verify farm belongs to this farmer
                    $farm = Farm::where('id', $noteData['farm_id'])
                               ->where('farmer_id', $farmer->id)
                               ->first();

                    if (!$farm) {
                        $errors[] = [
                            'index' => $index,
                            'error' => "Farm {$noteData['farm_id']} not found or doesn't belong to farmer",
                            'farm_id' => $noteData['farm_id']
                        ];
                        $skippedCount++;
                        continue;
                    }

                    // Prevent duplicate notes (same title, farm, and creation time)
                    $existingNote = Note::where('farmer_id', $farmer->id)
                                       ->where('farm_id', $noteData['farm_id'])
                                       ->where('title', $noteData['title'])
                                       ->where('created_at', $noteData['created_at'])
                                       ->first();

                    if ($existingNote) {
                        // Update existing note if content is different
                        if ($existingNote->content !== $noteData['content'] || 
                            $existingNote->updated_at->format('Y-m-d H:i:s') !== Carbon::parse($noteData['updated_at'])->format('Y-m-d H:i:s')) {
                            
                            $existingNote->update([
                                'content' => $noteData['content'],
                                'updated_at' => $noteData['updated_at']
                            ]);
                            $syncedCount++;
                        } else {
                            $skippedCount++;
                        }
                        continue;
                    }

                    // Create new note
                    Note::create([
                        'farmer_id' => $farmer->id,
                        'farm_id' => $noteData['farm_id'],
                        'title' => $noteData['title'],
                        'content' => $noteData['content'],
                        'created_at' => $noteData['created_at'],
                        'updated_at' => $noteData['updated_at']
                    ]);

                    $syncedCount++;

                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => 'Failed to process note: ' . $e->getMessage(),
                        'title' => $noteData['title'] ?? 'Unknown'
                    ];
                    $skippedCount++;
                }
            }

            DB::commit();

            Log::info('Notes sync completed', [
                'farmer_id' => $farmer->id,
                'total_notes' => count($notesData),
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notes synchronized successfully',
                'data' => [
                    'sync_timestamp' => now()->toISOString(),
                    'total_received' => count($notesData),
                    'synced_count' => $syncedCount,
                    'skipped_count' => $skippedCount,
                    'errors' => $errors
                ]
            ]);

        } catch (Exception $e) {
            DB::rollback();
            
            Log::error('Notes sync failed', [
                'farmer_id' => $farmer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => 'SYNC_FAILED',
                'message' => 'Failed to sync notes',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all notes for the authenticated farmer
     */
    public function index(Request $request)
    {
        $farmer = $this->getAuthenticatedFarmer($request);

        // Parse query parameters
        $perPage = min($request->get('per_page', 50), 100); // Max 100 notes per page
        $farmId = $request->get('farm_id');
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Build query
        $query = Note::where('farmer_id', $farmer->id)
                    ->with('farm:id,name,district,village');

        // Filter by farm if specified
        if ($farmId) {
            $query->where('farm_id', $farmId);
        }

        // Search in title and content
        if ($search) {
            $query->search($search);
        }

        // Sort
        $allowedSortFields = ['created_at', 'updated_at', 'title'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $notes = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'notes' => $notes->items(),
                'pagination' => [
                    'current_page' => $notes->currentPage(),
                    'last_page' => $notes->lastPage(),
                    'per_page' => $notes->perPage(),
                    'total' => $notes->total(),
                    'from' => $notes->firstItem(),
                    'to' => $notes->lastItem()
                ]
            ]
        ]);
    }

    /**
     * Get notes for a specific farm
     */
    public function farmNotes(Request $request, Farm $farm)
    {
        if ($ownershipError = $this->verifyFarmOwnership($request, $farm)) {
            return $ownershipError;
        }

        $perPage = min($request->get('per_page', 20), 50);
        $search = $request->get('search');

        $query = $farm->notes();

        if ($search) {
            $query->search($search);
        }

        $notes = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'farm' => [
                    'id' => $farm->id,
                    'name' => $farm->name,
                    'full_location' => $farm->full_location
                ],
                'notes' => $notes->items(),
                'pagination' => [
                    'current_page' => $notes->currentPage(),
                    'last_page' => $notes->lastPage(),
                    'per_page' => $notes->perPage(),
                    'total' => $notes->total()
                ]
            ]
        ]);
    }

    /**
     * Get note statistics for the farmer
     */
    public function statistics(Request $request)
    {
        $farmer = $this->getAuthenticatedFarmer($request);

        $stats = [
            'total_notes' => $farmer->notes()->count(),
            'notes_this_month' => $farmer->notes()
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
            'notes_this_week' => $farmer->notes()
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->count(),
            'notes_by_farm' => $farmer->farms()
                ->withCount('notes')
                ->get()
                ->map(function($farm) {
                    return [
                        'farm_id' => $farm->id,
                        'farm_name' => $farm->name,
                        'notes_count' => $farm->notes_count
                    ];
                }),
            'recent_activity' => $farmer->notes()
                ->latest()
                ->limit(5)
                ->with('farm:id,name')
                ->get()
                ->map(function($note) {
                    return [
                        'title' => $note->title,
                        'farm_name' => $note->farm->name,
                        'created_at' => $note->created_at->toISOString()
                    ];
                })
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}





   