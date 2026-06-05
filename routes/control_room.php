<?php

use App\Http\Controllers\Admin\AgentControlRoom\{AgentDiagnosticsController, ControlRoomController, EventStreamController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/control-room')
    ->name('admin.control_room.')
    ->group(function () {

    Route::get('/',                    [ControlRoomController::class, 'index'])->name('index');

    Route::get('/stream',              [EventStreamController::class, 'stream'])->name('stream');

    Route::get('/diagnostics',         [AgentDiagnosticsController::class, 'systemHealth'])->name('diagnostics');
    Route::post('/test-ai-provider',   [AgentDiagnosticsController::class, 'testAiProvider'])->name('test_ai');
    Route::post('/test-agent',         [AgentDiagnosticsController::class, 'testAgent'])->name('test_agent');
    Route::post('/test-ambassador-chat', [AgentDiagnosticsController::class, 'testAmbassadorChat'])->name('test_ambassador_chat');

    Route::post('/agents/{agent}/pause',               [ControlRoomController::class, 'pauseAgent'])->name('agents.pause');
    Route::post('/agents/{agent}/resume',              [ControlRoomController::class, 'resumeAgent'])->name('agents.resume');
    Route::post('/agents/{agent}/supervise',           [ControlRoomController::class, 'superviseAgent'])->name('agents.supervise');
    Route::post('/agents/{agent}/kill-switch',         [ControlRoomController::class, 'killSwitch'])->name('agents.kill');
    Route::post('/agents/{agent}/release-kill-switch', [ControlRoomController::class, 'releaseKillSwitch'])->name('agents.release');
    Route::patch('/agents/{agent}/spend-cap',          [ControlRoomController::class, 'updateSpendCap'])->name('agents.spend_cap');

    Route::get('/hitl',                    [ControlRoomController::class, 'hitlQueue'])->name('hitl.index');
    Route::post('/hitl/{task}/execute',    [ControlRoomController::class, 'executeHitlTask'])->name('hitl.execute');
    Route::post('/hitl/{task}/skip',       [ControlRoomController::class, 'skipHitlTask'])->name('hitl.skip');
    Route::patch('/hitl/{task}/reassign',  [ControlRoomController::class, 'reassignHitlTask'])->name('hitl.reassign');
    Route::get('/hitl/{task}',             [ControlRoomController::class, 'showHitlTask'])->name('hitl.show');

    Route::get('/events/{event}',  [ControlRoomController::class, 'eventDetail'])->name('events.show');

    Route::post('/trigger',        [ControlRoomController::class, 'triggerAgentJob'])->name('trigger');

    Route::get('/cost-analytics',  [ControlRoomController::class, 'costAnalytics'])->name('cost_analytics');
});
