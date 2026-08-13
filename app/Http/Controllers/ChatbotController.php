<?php

namespace App\Http\Controllers;

use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Sage" chatbot — POST /api/chat, auth-only (CLAUDE.md
 * §7), same as the plain search endpoint. Stateless: the client resends
 * the running conversation as `history` on every request; nothing is
 * persisted server-side for v1 — no chat-log table, no schema change.
 *
 * Always returns 200 with a body — ChatbotService::reply() never throws
 * for an unavailable Gemini API (missing key, rate limit, network
 * error); it degrades to a plain "here's what I found in the database"
 * answer instead (see its docblock), so the frontend never needs a
 * separate error-response code path for that expected case.
 *
 * See ChatbotQueryClassifier/ChatbotRetrievalService/ChatbotService for
 * the actual classify -> retrieve -> prompt -> call Gemini pipeline.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot)
    {
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        return response()->json(
            $this->chatbot->reply($validated['message'], $validated['history'] ?? [], $request->user()->role)
        );
    }
}
