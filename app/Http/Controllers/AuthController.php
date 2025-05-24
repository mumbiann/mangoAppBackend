<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use App\Models\Farmer;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'required|email|unique:farmers,email',
            'password' => 'required|string|min:8',
            'district' => 'required|string',
            'village' => 'required|string'
        ]);

        $farmer = Farmer::create([
            ...$validated,
            'password' => bcrypt($validated['password'])
        ]);

        $token = $farmer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'data' => [
                'farmer_id' => $farmer->id,
                'name' => $farmer->name,
                'access_token' => $token,
                'token_expires_at' => now()->addDays(90),
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $farmer = Farmer::where('email', $validated['email'])->first();

        if (!$farmer || !Hash::check($validated['password'], $farmer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $farmer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'farmer_id' => $farmer->id,
                'name' => $farmer->name,
                'access_token' => $token,
                'token_expires_at' => now()->addDays(90),
            ]
        ]);
    }

    public function refresh(Request $request)
    {
        $farmer = $request->user();

        $farmer->tokens()->delete(); // Optional: revoke previous token

        $token = $farmer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed',
            'data' => [
                'access_token' => $token,
                'token_expires_at' => now()->addDays(90),
            ]
        ]);
    }
    }

