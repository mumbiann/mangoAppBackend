<?php

namespace App\Http\Traits;
use App\Models\Farmer;
use Illuminate\Http\Request;

trait HasAuthenticatedFarmer
{
    /**
     * Get the authenticated farmer from request
     */
    protected function getAuthenticatedFarmer(Request $request): Farmer
    {
        return $request->get('farmer');
    }

    /**
     * Check if farmer owns a specific farm
     */
    protected function farmerOwnsFarm(Farmer $farmer, $farm): bool
    {
        return $farm->farmer_id === $farmer->id;
    }

    /**
     * Get farmer's farms query
     */
    protected function getFarmerFarmsQuery(Farmer $farmer)
    {
        return $farmer->farms();
    }

    /**
     * Verify farm ownership and return farm or 403 error
     */
    protected function verifyFarmOwnership(Request $request, $farm)
    {
        $farmer = $this->getAuthenticatedFarmer($request);
        
        if (!$this->farmerOwnsFarm($farmer, $farm)) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNAUTHORIZED',
                'message' => 'This farm does not belong to you'
            ], 403);
        }
        
        return null; // No error, ownership verified
    }
}
