<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>AI Provider Alert</title></head>
<body style="font-family:system-ui,sans-serif;background:#0a0a0b;color:#e0e0e0;padding:30px">
    <div style="max-width:600px;margin:0 auto;background:#121214;border:1px solid #2a2a2a;border-radius:12px;overflow:hidden">

        <div style="background:#ef4444;padding:20px;text-align:center">
            <h1 style="color:#fff;font-size:20px;margin:0">AI Provider Downtime Alert</h1>
            <p style="color:#fecaca;margin:8px 0 0;font-size:14px">{{ $downTime }}</p>
        </div>

        <div style="padding:24px">
            <p style="font-size:15px;margin:0 0 16px">
                <strong>{{ $affectedProviders }}</strong> is currently unreachable.
                All active agents have been switched to <strong>supervised mode</strong>.
            </p>

            <table style="width:100%;border-collapse:collapse;margin:0 0 24px">
                <tr style="border-bottom:1px solid #2a2a2a">
                    <td style="padding:8px;font-weight:600">OpenAI</td>
                    <td style="padding:8px;text-align:right;color:{{ $openAiStatus === 'Online' ? '#4ade80' : '#ef4444' }}">
                        {{ $openAiStatus }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px;font-weight:600">Anthropic</td>
                    <td style="padding:8px;text-align:right;color:{{ $anthropicStatus === 'Online' ? '#4ade80' : '#ef4444' }}">
                        {{ $anthropicStatus }}
                    </td>
                </tr>
            </table>

            <p style="color:#a0a0a0;font-size:13px;margin:0 0 16px">
                Human tasks that would normally be handled by AI agents are now routed to the
                <strong>Human Task Queue</strong> in the Control Room. Please review and execute
                pending tasks manually until AI connectivity is restored.
            </p>

            <a href="{{ $controlRoomUrl }}"
               style="display:inline-block;background:#2da48e;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">
                Open Control Room
            </a>

            <p style="color:#555;font-size:12px;margin:24px 0 0">
                Agents will automatically return to active mode once connectivity is restored.
                You can also manually resume agents from the Control Room agent bar.
            </p>
        </div>

        <div style="background:#121214;border-top:1px solid #2a2a2a;padding:16px 24px;text-align:center">
            <span style="color:#444;font-size:11px">Maids.ng Mission Control &bull; Automated Alert</span>
        </div>
    </div>
</body>
</html>
