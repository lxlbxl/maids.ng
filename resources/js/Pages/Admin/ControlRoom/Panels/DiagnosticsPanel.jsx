import { useState, useEffect } from "react";
import axios from "axios";

export default function DiagnosticsPanel() {
    const [health, setHealth] = useState(null);
    const [loading, setLoading] = useState(false);
    const [chatTest, setChatTest] = useState(null);
    const [chatLoading, setChatLoading] = useState(false);

    const runDiagnostics = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/admin/control-room/diagnostics');
            setHealth(res.data);
        } catch (e) {
            setHealth({
                success: false,
                checks: {
                    connection: { status: 'error', message: e.message }
                },
                timestamp: new Date().toISOString()
            });
        } finally {
            setLoading(false);
        }
    };

    const testAmbassadorChat = async () => {
        setChatLoading(true);
        try {
            const res = await axios.post('/admin/control-room/test-ambassador-chat', {
                message: 'Hello, what is Maids.ng?'
            });
            setChatTest({ success: true, ...res.data });
        } catch (e) {
            setChatTest({
                success: false,
                error: e.response?.data?.error || e.message,
                class: e.response?.data?.class,
                file: e.response?.data?.file,
                logs: e.response?.data?.logs || [],
            });
        } finally {
            setChatLoading(false);
        }
    };

    useEffect(() => {
        runDiagnostics();
    }, []);

    const statusIcon = (status) => {
        if (status === 'ok') return '✓';
        if (status === 'error') return '✗';
        if (status === 'warning') return '⚠';
        return 'ℹ';
    };

    const statusColor = (status) => {
        if (status === 'ok') return 'text-green-400';
        if (status === 'error') return 'text-red-400';
        if (status === 'warning') return 'text-yellow-400';
        return 'text-blue-400';
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-white font-semibold text-sm">System Diagnostics</h3>
                <div className="flex gap-2">
                    <button
                        onClick={testAmbassadorChat}
                        disabled={chatLoading}
                        className="px-3 py-1.5 text-xs bg-teal-600 hover:bg-teal-500 text-white rounded-lg disabled:opacity-40 transition-colors"
                    >
                        {chatLoading ? 'Testing Chat...' : 'Test Webchat'}
                    </button>
                    <button
                        onClick={runDiagnostics}
                        disabled={loading}
                        className="px-3 py-1.5 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded-lg disabled:opacity-40 transition-colors"
                    >
                        {loading ? 'Scanning...' : 'Refresh'}
                    </button>
                </div>
            </div>

            {health && (
                <div className="space-y-2">
                    {Object.entries(health.checks || {}).map(([key, check]) => (
                        <div key={key} className="flex items-start gap-2 text-sm">
                            <span className={`font-bold ${statusColor(check.status)}`}>
                                {statusIcon(check.status)}
                            </span>
                            <div>
                                <span className="text-gray-300 capitalize">{key.replace(/_/g, ' ')}</span>
                                <span className="text-gray-500 ml-2">{check.message}</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {chatTest && (
                <div className={`mt-4 p-3 rounded-lg text-sm ${chatTest.success ? 'bg-green-900/20 border border-green-800' : 'bg-red-900/20 border border-red-800'}`}>
                    <div className="font-semibold mb-1 text-white">
                        {chatTest.success ? 'Webchat Test Passed' : 'Webchat Test Failed'}
                    </div>
                    {chatTest.success && !chatTest._error ? (
                        <div className="text-gray-300">
                            <p><span className="text-gray-500">Reply:</span> {chatTest.reply}</p>
                            <p><span className="text-gray-500">Conversation ID:</span> {chatTest.conversation_id}</p>
                            {(chatTest._debug_model || chatTest._debug_model_base) && (
                                <div className="mt-2 text-xs text-yellow-400">
                                    <p>Model: {chatTest._debug_model} (base: {chatTest._debug_model_base})</p>
                                    <p>Payload keys: {chatTest._debug_payload_keys?.join(', ')}</p>
                                </div>
                            )}
                            {chatTest._step_logs?.length > 0 && (
                                <div className="mt-3 p-2 bg-black/30 rounded text-xs font-mono text-gray-400 space-y-0.5">
                                    <div className="text-gray-500 mb-1">Execution trace:</div>
                                    {chatTest._step_logs.map((l, i) => <div key={i}>{l}</div>)}
                                </div>
                            )}
                            {chatTest.logs?.length > 0 && (
                                <div className="mt-2 text-xs text-gray-500">
                                    {chatTest.logs.map((l, i) => <div key={i}>• {l}</div>)}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-red-300">
                            {chatTest._error && (
                                <>
                                    <p className="font-mono text-xs break-all font-semibold">{chatTest._error}</p>
                                    {chatTest._file && <p className="text-xs text-gray-500 mt-1">{chatTest._file}</p>}
                                </>
                            )}
                            {!chatTest._error && <p className="font-mono text-xs break-all">{chatTest.error}</p>}
                            {!chatTest._error && chatTest.file && <p className="text-xs text-gray-500 mt-1">{chatTest.file}</p>}
                            {(chatTest._debug_model || chatTest._debug_model_base) && (
                                <div className="mt-2 text-xs text-yellow-400">
                                    <p>Model: {chatTest._debug_model} (base: {chatTest._debug_model_base})</p>
                                    <p>Payload keys: {chatTest._debug_payload_keys?.join(', ')}</p>
                                </div>
                            )}
                            {chatTest._step_logs?.length > 0 && (
                                <div className="mt-3 p-2 bg-black/30 rounded text-xs font-mono text-gray-400 space-y-0.5">
                                    <div className="text-gray-500 mb-1">Execution trace:</div>
                                    {chatTest._step_logs.map((l, i) => <div key={i}>{l}</div>)}
                                </div>
                            )}
                            {chatTest.logs?.length > 0 && (
                                <div className="mt-2 text-xs text-gray-500">
                                    {chatTest.logs.map((l, i) => <div key={i}>• {l}</div>)}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
