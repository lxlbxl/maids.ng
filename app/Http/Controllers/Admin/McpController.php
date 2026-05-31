<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpServer;
use App\Services\Mcp\McpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class McpController extends Controller
{
    /**
     * Display a listing of the MCP servers.
     */
    public function index(Request $request)
    {
        $servers = McpServer::orderBy('name')->get();

        // Support AJAX refresh from Settings page
        if ($request->wantsJson()) {
            return response()->json(['servers' => $servers]);
        }

        return Inertia::render('Admin/Settings', [
            'mcpServers' => $servers,
        ]);
    }

    /**
     * Store a newly created MCP server.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:mcp_servers,name',
            'base_url' => 'required|url',
            'auth_token' => 'nullable|string',
        ]);
        McpServer::create($validated);
        return back()->with('success', 'MCP server added successfully.');
    }

    /**
     * Update an existing MCP server.
     */
    public function update($id, Request $request)
    {
        $server = McpServer::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:mcp_servers,name,' . $server->id,
            'base_url' => 'required|url',
            'auth_token' => 'nullable|string',
        ]);
        $server->update($validated);
        return back()->with('success', 'MCP server updated successfully.');
    }

    /**
     * Delete a MCP server.
     */
    public function destroy($id)
    {
        $server = McpServer::findOrFail($id);
        $server->delete();
        return back()->with('success', 'MCP server removed.');
    }

    /**
     * Test connection / ping the MCP server and return usage snippet.
     */
    public function testConnection($id, McpService $mcpService)
    {
        $server = McpServer::findOrFail($id);
        try {
            $pingResult = $mcpService->ping($server);
            $usage = $mcpService->generateUsageSnippet($server);
            return response()->json([
                'success' => true,
                'ping' => $pingResult,
                'usage' => $usage,
            ]);
        } catch (\Exception $e) {
            Log::error('MCP test connection failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
?>
