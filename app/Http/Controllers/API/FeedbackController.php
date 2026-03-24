<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    // Qualquer utilizador (ou anónimo) pode enviar feedback
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:150',
            'type'    => 'required|in:reclamacao,sugestao,elogio,outro',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
        ]);

        $validated['user_id'] = auth('sanctum')->id();

        // Se o utilizador está autenticado, preenche nome/email automaticamente
        if ($user = auth('sanctum')->user()) {
            $validated['name']  = $validated['name']  ?? $user->name;
            $validated['email'] = $validated['email'] ?? $user->email;
        }

        Feedback::create($validated);

        return response()->json(['message' => 'Mensagem enviada! Iremos analisar e dar a devida atenção.'], 201);
    }

    // Listagem para admins
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::with('user:id,name,email')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type,   fn($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($feedbacks);
    }

    // Admin actualiza status
    public function update(Request $request, Feedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'required|in:novo,em_analise,resolvido',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $feedback->update($validated);

        return response()->json(['message' => 'Actualizado.']);
    }
}
