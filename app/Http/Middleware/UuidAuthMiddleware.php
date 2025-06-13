<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Farmer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UuidAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get UUID from request header
        $uuid = $request->header('X-User-ID');

        // Check if UUID is provided
        if (!$uuid) {
            Log::warning('UUID missing in request', [
                'url' => $request->url(),
                'method' => $request->method(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'UUID_REQUIRED',
                'message' => 'User UUID is required in X-User-ID header'
            ], 400);
        }

        // Validate UUID format
        if (!Str::isUuid($uuid)) {
            Log::warning('Invalid UUID format provided', [
                'uuid' => $uuid,
                'url' => $request->url(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_UUID_FORMAT',
                'message' => 'Invalid UUID format provided'
            ], 400);
        }

        // Find farmer by UUID
        $farmer = Farmer::where('uuid', $uuid)->first();

        if (!$farmer) {
            Log::warning('Farmer not found for UUID', [
                'uuid' => $uuid,
                'url' => $request->url(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'FARMER_NOT_FOUND',
                'message' => 'Farmer not found with provided UUID'
            ], 404);
        }

        // Log successful authentication for monitoring
        Log::info('Farmer authenticated successfully', [
            'farmer_id' => $farmer->id,
            'farmer_name' => $farmer->name,
            'uuid' => $uuid,
            'url' => $request->url()
        ]);

        // Add farmer to request attributes for use in controllers
        $request->attributes->set('farmer', $farmer);
        
        // Also add to request for easier access in controllers
        $request->merge(['authenticated_farmer' => $farmer]);


        return $next($request);
    }
}
