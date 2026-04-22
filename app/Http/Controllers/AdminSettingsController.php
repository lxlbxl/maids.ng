<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $settings = \App\Models\Setting::all()->groupBy('group');
        $aiService = new \App\Services\Ai\AiService();

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'aiManifest' => $aiService->getProviderManifest()
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($data['settings'] as $key => $config) {
            \App\Models\Setting::set(
                $key, 
                $config['value'], 
                $config['group'] ?? 'general', 
                $config['is_encrypted'] ?? false
            );
        }

        return back()->with('success', 'System settings updated successfully.');
    }
}
