<?php

namespace App\Http\Controllers;

use App\Services\Plaid\PlaidWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PlaidWebhookController extends Controller
{
    public function __invoke(Request $request, PlaidWebhookService $webhookService): JsonResponse
    {
        try {
            $webhookService->verifySecret($request->header('Plaid-Webhook-Secret'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Unauthorized webhook request.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $webhookService->handleWebhook($request->all());

        return response()->json([
            'received' => true,
        ], Response::HTTP_OK);
    }
}
