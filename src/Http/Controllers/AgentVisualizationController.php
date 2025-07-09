<?php

namespace Sapiensly\OpenaiAgents\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

class AgentVisualizationController extends Controller
{
    public function index(Request $request)
    {
        if (!Config::get('agents.visualization.enabled', false)) {
            abort(404, 'Agent visualization is disabled');
        }
        return view('sapiensly-openai-agents::visualization.dashboard');
    }

    // Endpoint para métricas en vivo (AJAX polling)
    public function metrics(Request $request)
    {
        if (!Config::get('agents.visualization.enabled', false)) {
            abort(404, 'Agent visualization is disabled');
        }
        // Aquí se puede mejorar: obtener métricas reales del sistema
        $metrics = [
            'active_agents' => 3,
            'handoffs' => 12,
            'success_rate' => 0.97,
            'avg_response_time' => 1.2,
            'parallel_handoffs' => 2,
            'errors' => 0,
            'traces' => [],
        ];
        return response()->json($metrics);
    }

    // Endpoint para grafo de handoffs (opcional)
    public function handoffGraph(Request $request)
    {
        if (!Config::get('agents.visualization.enabled', false)) {
            abort(404, 'Agent visualization is disabled');
        }
        // Ejemplo: grafo estático, en real usar datos de la conversación
        $graph = [
            'nodes' => [
                ['id' => 'general', 'label' => 'General'],
                ['id' => 'math', 'label' => 'Math'],
                ['id' => 'history', 'label' => 'History'],
            ],
            'edges' => [
                ['from' => 'general', 'to' => 'math'],
                ['from' => 'math', 'to' => 'history'],
                ['from' => 'history', 'to' => 'general'],
            ],
        ];
        return response()->json($graph);
    }
} 