<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmandateRequest; // Hamari custom Request class
use App\Services\EmandateService; // Hamari Service class
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class EmandateController extends Controller
{
    protected $emandateService;

    public function __construct(EmandateService $emandateService)
    {
        $this->emandateService = $emandateService;
    }

    /**
     * Create a new eMandate.
     *
     * @param CreateEmandateRequest $request
     * @return JsonResponse
     */
    public function create(CreateEmandateRequest $request): JsonResponse
    {
        try {
            // Service ab seedha URL string return karegi
            $authorizationUrl = $this->emandateService->createMandate($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Authorization link generated successfully.',
                'authorization_url' => $authorizationUrl, // Seedha URL use karein
            ]);

        } catch (Exception $e) {
            Log::error('eMandate creation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the mandate.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}