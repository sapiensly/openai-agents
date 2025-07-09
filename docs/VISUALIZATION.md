# Agent Visualization Dashboard for Laravel OpenAI Agents

## Overview

The Agent Visualization Dashboard provides a real-time, web-based interface for monitoring, analyzing, and debugging agent activity, handoffs, and conversation flows in your Laravel application. It is designed to be more powerful and user-friendly than the CLI-based visualization tools of the Python SDK.

**Key Features:**
- Real-time live metrics (active agents, handoffs, success rate, response times, errors)
- Interactive handoff graph (SVG/D3.js, extensible)
- Agent activity panel
- Tracing and export tools
- Fully configurable and publishable views
- Seamless integration with Laravel middleware and config

---

## Why is this better than the Python SDK?
- **Web UI:** Modern, responsive dashboard instead of CLI-only stats.
- **Live Metrics:** Real-time polling and visualization of agent activity, handoffs, and errors.
- **Graph Visualization:** Visual handoff graph for understanding agent transitions and parallel handoffs.
- **Extensibility:** Easily add new panels, metrics, or visualizations.
- **Laravel-native:** Uses middleware, config, and view publishing for full customization.
- **Export & Tracing:** Export conversation and trace data for audits or debugging.

---

## How it Works

- **Routes and controllers** for the dashboard and its AJAX endpoints are registered only if `agents.visualization.enabled` is true in your config.
- **Views** are loaded from the package by default, but can be published and customized in your app.
- **Metrics and graph data** are fetched via AJAX from `/agents/visualization/metrics` and `/agents/visualization/handoff-graph`.
- **All logic and UI** are contained within the package for easy upgrades and maintenance.

---

## Configuration Example

In `config/agents.php`:

```php
'visualization' => [
    'enabled' => env('AGENTS_VISUALIZATION_ENABLED', false),
    'route' => env('AGENTS_VISUALIZATION_ROUTE', '/agents/visualization'),
    'middleware' => ['web'],
    'views_publishable' => true,
    'metrics_refresh_interval' => 2, // seconds
    'features' => [
        'handoff_graph' => true,
        'live_metrics' => true,
        'agent_activity' => true,
        'tracing' => true,
        'conversation_export' => true,
        'parallel_handoff_viz' => true,
        'error_analysis' => true,
    ],
],
```

Enable in your `.env`:
```
AGENTS_VISUALIZATION_ENABLED=true
```

---

## Publishing Views

To customize the dashboard UI, publish the views:

```
php artisan vendor:publish --tag=agents-views
```

Edit the Blade files in `resources/views/vendor/sapiensly-openai-agents/visualization/` as needed.

---

## Endpoints and Architecture

- **Dashboard:** `GET /agents/visualization` — Main dashboard UI
- **Live Metrics:** `GET /agents/visualization/metrics` — Returns JSON with current metrics
- **Handoff Graph:** `GET /agents/visualization/handoff-graph` — Returns JSON graph data

All endpoints are protected by the middleware defined in your config (default: `web`).

---

## Extending the Dashboard

You can add new panels, metrics, or visualizations by:
1. Publishing and editing the Blade views.
2. Adding new controller methods and AJAX endpoints in your own app or via PRs to the package.
3. Extending the metrics logic in `AgentVisualizationController` to pull real data from your agents, handoff orchestrator, or tracing system.
4. Using JavaScript libraries (e.g., D3.js, Chart.js) for advanced visualizations.

**Example: Add a new metric panel**
- Add a new `<div>` in the Blade view.
- Add a new key to the metrics JSON in the controller.
- Update the JS to display the new metric.

---

## Best Practices
- Keep the dashboard enabled only in development or for admin users.
- Use the export and tracing features for audits and debugging.
- Regularly update the package to benefit from new visualization features.
- Contribute improvements or new visualizations via PRs!

---

## Troubleshooting
- If the dashboard does not appear, check that `AGENTS_VISUALIZATION_ENABLED=true` and clear your config cache.
- If you customize views, re-publish after package updates to get new features.

---

## Advanced Code Examples

### 1. Custom Metric: Tool Usage Count

In your published Blade view:
```blade
<div class="card">
    <h2>Tool Usage</h2>
    <div id="stat-tool-usage">-</div>
</div>
```

In your controller:
```php
public function metrics(Request $request)
{
    // ...
    $metrics['tool_usage'] = ToolRegistry::getAllTools() ? count(ToolRegistry::getAllTools()) : 0;
    return response()->json($metrics);
}
```

In your JS:
```js
fetchMetrics = function() {
    // ...
    document.getElementById('stat-tool-usage').textContent = data.tool_usage;
}
```

### 2. Adding a Custom JS Panel (Chart.js)

In your Blade view:
```blade
<canvas id="responseTimeChart" width="400" height="120"></canvas>
```

In your JS:
```js
let ctx = document.getElementById('responseTimeChart').getContext('2d');
let chart = new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Response Time', data: [] }] },
    options: { responsive: true }
});
// On fetchMetrics, update chart.data.datasets[0].data and chart.update();
```

### 3. Integrating with Tracing

In your controller:
```php
$metrics['traces'] = Tracing::getRecentTraces(10); // Implement this in your Tracing class
```

### 4. Exporting Conversation

Add a new route and controller method:
```php
Route::get('/agents/visualization/export', [AgentVisualizationController::class, 'export'])->name('agents.visualization.export');

public function export(Request $request) {
    $data = ...; // Gather conversation data
    return response()->json($data);
}
```

In your JS:
```js
document.getElementById('export-btn').onclick = function() {
    fetch('/agents/visualization/export').then(r => r.json()).then(data => {
        // Download as file or show modal
    });
}
```

---

## Screenshots

> ![Dashboard Screenshot Placeholder](https://via.placeholder.com/1200x600?text=Agent+Visualization+Dashboard)
> 
> _Replace with real screenshots of your dashboard in action!_

---

## Questions?
Open an issue or PR in the repository, or contact the maintainers for help extending the dashboard. 