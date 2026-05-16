<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Channels\ChannelManager;
use App\Services\Inbox\UnifiedInbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InboxController extends Controller
{
    public function __construct(
        private UnifiedInbox $inbox,
        private ChannelManager $channels,
    ) {}

    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->inbox->list(
                filters: $request->only(['platform', 'status', 'escalated', 'unanswered', 'q']),
                limit:   (int) $request->input('limit', 50),
            ),
        ]);
    }

    public function show(int $id)
    {
        return response()->json($this->inbox->thread($id));
    }

    /** Send a manual reply (employee takeover). */
    public function reply(Request $request, int $id)
    {
        $request->validate(['body' => 'required|string|max:4000']);
        $conv = Conversation::with('customer')->findOrFail($id);

        $driver = $this->channels->driver($conv->platform);
        $result = $driver->sendText($conv->thread_id, $request->string('body'));

        Message::create([
            'conversation_id'   => $conv->id,
            'customer_id'       => $conv->customer_id,
            'platform_msg_id'   => $result->platformMsgId,
            'direction'         => Message::DIRECTION_OUT,
            'kind'              => 'text',
            'body'              => $request->string('body'),
            'is_ai'             => false,
            'author_employee_id'=> $request->user()?->id,
            'sent_at'           => now(),
        ]);
        $conv->update(['last_outbound_at' => now()]);

        AuditLog::record('employee', 'manual.reply', 'conversation', $conv->id, ['ok' => $result->ok], $request->user()?->id);

        return response()->json(['ok' => $result->ok]);
    }

    public function takeover(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update([
            'ai_paused'            => true,
            'assigned_employee_id' => $request->user()?->id,
        ]);
        AuditLog::record('employee', 'takeover', 'conversation', $conv->id, [], $request->user()?->id);
        return response()->json(['ok' => true]);
    }

    public function release(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update([
            'ai_paused'            => false,
            'escalated'            => false,
            'escalation_reason'    => null,
            'assigned_employee_id' => null,
        ]);
        AuditLog::record('employee', 'release', 'conversation', $conv->id, [], $request->user()?->id);
        return response()->json(['ok' => true]);
    }

    public function updateMemory(Request $request, int $id)
    {
        $request->validate(['memory' => 'required|array']);
        $conv = Conversation::with('customer')->findOrFail($id);
        $conv->customer->patchMemory($request->input('memory'));
        return response()->json(['ok' => true, 'memory' => $conv->customer->fresh()->profile_json]);
    }

    public function setStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|string|in:new,interested,product_recommended,waiting,payment_pending,order_created,converted,escalated,lost']);
        $conv = Conversation::findOrFail($id);
        $conv->update(['lead_status' => $request->input('status')]);
        return response()->json(['ok' => true]);
    }

    public function flagSpam(Request $request, int $id)
    {
        $conv = Conversation::with('customer')->findOrFail($id);
        $conv->customer->update(['is_spam' => true]);
        $conv->update(['ai_paused' => true, 'lead_status' => Conversation::STATUS_LOST]);
        AuditLog::record('employee', 'flag.spam', 'conversation', $id, [], $request->user()?->id);
        return response()->json(['ok' => true]);
    }
}
