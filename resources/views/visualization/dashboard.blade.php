@extends('layouts.app')

@section('title', 'Agent Visualization Dashboard')

@section('content')
<div id="agent-viz-app" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
    <h1 style="font-size:2.2rem; font-weight:700; margin-bottom:1.5rem; color:#222;">Agent Visualization Dashboard</h1>
    <div class="dashboard-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
        <div>
            <div class="card" style="background:#fff; border-radius:10px; box-shadow:0 2px 8px #0001; padding:2rem; margin-bottom:2rem;">
                <h2 style="font-size:1.2rem; font-weight:600; margin-bottom:1rem;">Live Metrics</h2>
                <div id="live-metrics">
                    <div class="metrics-row" style="display:flex; gap:1.5rem; flex-wrap:wrap;">
                        <div><span class="stat-value" id="stat-active-agents">-</span><div class="stat-label">Active Agents</div></div>
                        <div><span class="stat-value" id="stat-handoffs">-</span><div class="stat-label">Handoffs</div></div>
                        <div><span class="stat-value" id="stat-success-rate">-</span><div class="stat-label">Success Rate</div></div>
                        <div><span class="stat-value" id="stat-avg-response">-</span><div class="stat-label">Avg. Response (s)</div></div>
                        <div><span class="stat-value" id="stat-parallel-handoffs">-</span><div class="stat-label">Parallel Handoffs</div></div>
                        <div><span class="stat-value" id="stat-errors">-</span><div class="stat-label">Errors</div></div>
                    </div>
                </div>
            </div>
            <div class="card" style="background:#fff; border-radius:10px; box-shadow:0 2px 8px #0001; padding:2rem;">
                <h2 style="font-size:1.2rem; font-weight:600; margin-bottom:1rem;">Agent Activity</h2>
                <div id="agent-activity">
                    <ul id="agent-activity-list" style="list-style:none; padding:0; margin:0;">
                        <li style="color:#888;">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
        <div>
            <div class="card" style="background:#fff; border-radius:10px; box-shadow:0 2px 8px #0001; padding:2rem; margin-bottom:2rem;">
                <h2 style="font-size:1.2rem; font-weight:600; margin-bottom:1rem;">Handoff Graph</h2>
                <div id="handoff-graph" style="height:320px; background:#f8f9fa; border-radius:8px; border:1px solid #eee; display:flex; align-items:center; justify-content:center;">
                    <span style="color:#aaa;">Loading graph...</span>
                </div>
            </div>
            <div class="card" style="background:#fff; border-radius:10px; box-shadow:0 2px 8px #0001; padding:2rem;">
                <h2 style="font-size:1.2rem; font-weight:600; margin-bottom:1rem;">Traces & Export</h2>
                <div id="traces" style="max-height:120px; overflow:auto; font-size:0.95rem; color:#444; background:#f7f7f7; border-radius:6px; padding:1rem; margin-bottom:1rem;">No traces yet.</div>
                <button id="export-btn" style="background:#222; color:#fff; border:none; border-radius:5px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export Conversation</button>
            </div>
        </div>
    </div>
</div>
<script>
function fetchMetrics() {
    fetch("{{ route('agents.visualization.metrics') }}")
        .then(r => r.json())
        .then(data => {
            document.getElementById('stat-active-agents').textContent = data.active_agents;
            document.getElementById('stat-handoffs').textContent = data.handoffs;
            document.getElementById('stat-success-rate').textContent = (data.success_rate * 100).toFixed(1) + '%';
            document.getElementById('stat-avg-response').textContent = data.avg_response_time;
            document.getElementById('stat-parallel-handoffs').textContent = data.parallel_handoffs;
            document.getElementById('stat-errors').textContent = data.errors;
            // Agent activity
            let list = document.getElementById('agent-activity-list');
            list.innerHTML = '';
            if (data.active_agents_list && data.active_agents_list.length) {
                data.active_agents_list.forEach(a => {
                    let li = document.createElement('li');
                    li.textContent = a;
                    list.appendChild(li);
                });
            } else {
                let li = document.createElement('li');
                li.textContent = 'No active agents.';
                list.appendChild(li);
            }
            // Traces
            document.getElementById('traces').textContent = (data.traces && data.traces.length) ? data.traces.join('\n') : 'No traces yet.';
        });
}
function fetchGraph() {
    fetch("{{ route('agents.visualization.handoff-graph') }}")
        .then(r => r.json())
        .then(graph => {
            // Simple D3.js or SVG rendering (placeholder)
            let el = document.getElementById('handoff-graph');
            el.innerHTML = '<svg width="300" height="300"><circle cx="150" cy="60" r="30" fill="#e2e8f0" /><text x="150" y="65" text-anchor="middle" font-size="16">General</text><circle cx="60" cy="220" r="30" fill="#e2e8f0" /><text x="60" y="225" text-anchor="middle" font-size="16">Math</text><circle cx="240" cy="220" r="30" fill="#e2e8f0" /><text x="240" y="225" text-anchor="middle" font-size="16">History</text><line x1="150" y1="90" x2="60" y2="190" stroke="#bbb" stroke-width="2" /><line x1="60" y1="190" x2="240" y2="190" stroke="#bbb" stroke-width="2" /><line x1="240" y1="190" x2="150" y2="90" stroke="#bbb" stroke-width="2" /></svg>';
        });
}
document.addEventListener('DOMContentLoaded', function() {
    fetchMetrics();
    fetchGraph();
    setInterval(fetchMetrics, {{ config('agents.visualization.metrics_refresh_interval', 2) * 1000 }});
    document.getElementById('export-btn').onclick = function() {
        alert('Export not implemented yet.');
    };
});
</script>
@endsection 