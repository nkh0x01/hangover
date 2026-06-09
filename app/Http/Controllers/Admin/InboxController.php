<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Note;
use App\Services\AI\AiSuggestionService;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\MessengerDriver;
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
                filters: $request->only(['platform', 'status', 'escalated', 'unanswered', 'unread', 'q']),
                limit: (int) $request->input('limit', 50),
            ),
        ]);
    }

    /**
     * Open thread. Side effect: marks conversation as read (last_read_at = now).
     */
    public function show(int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['last_read_at' => now()]);
        return response()->json($this->inbox->thread($id));
    }

    /** Send a manual reply (employee takeover). */
    public function reply(Request $request, int $id)
    {
        $request->validate(['body' => 'required|string|max:4000']);
        $conv = Conversation::with('customer')->findOrFail($id);

        $driver = $this->channels->driver($conv->platform);
        $result = $driver->sendText($conv->thread_id, $request->string('body'));

        if (! $result->ok) {
            return response()->json([
                'ok' => false,
                'error' => $result->error ?? 'send_failed',
                'detail' => $result->raw ?? null,
            ], 422);
        }

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'customer_id' => $conv->customer_id,
            'platform_msg_id' => $result->platformMsgId,
            'direction' => Message::DIRECTION_OUT,
            'kind' => 'text',
            'body' => $request->string('body'),
            'is_ai' => false,
            'author_employee_id' => $request->user()?->id,
            'sent_at' => now(),
        ]);
        $conv->update(['last_outbound_at' => now(), 'last_read_at' => now()]);

        AuditLog::record('employee', 'manual.reply', 'conversation', $conv->id, ['msg_id' => $msg->id], $request->user()?->id);

        return response()->json([
            'ok' => true,
            'message' => [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'kind' => $msg->kind,
                'body' => $msg->body,
                'is_ai' => false,
                'author' => $request->user()?->only(['id', 'name']),
                'created_at' => $msg->created_at,
            ],
        ]);
    }

    /**
     * Generate an AI reply suggestion WITHOUT sending. Returns the text
     * for the agent to edit + send manually.
     */
    public function aiSuggest(int $id, AiSuggestionService $svc)
    {
        $conv = Conversation::with('customer')->findOrFail($id);
        $result = $svc->suggest($conv);
        if ($result['ok']) {
            AuditLog::record('employee', 'ai.suggest', 'conversation', $conv->id, ['model' => $result['model']], request()->user()?->id);
        }
        return response()->json($result);
    }

    public function takeover(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update([
            'ai_paused' => true,
            'assigned_employee_id' => $request->user()?->id,
        ]);
        AuditLog::record('employee', 'takeover', 'conversation', $conv->id, [], $request->user()?->id);

        return response()->json(['ok' => true]);
    }

    public function release(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update([
            'ai_paused' => false,
            'escalated' => false,
            'escalation_reason' => null,
            'assigned_employee_id' => null,
        ]);
        AuditLog::record('employee', 'release', 'conversation', $conv->id, [], $request->user()?->id);

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, int $id)
    {
        $request->validate(['employee_id' => 'nullable|integer|exists:employees,id']);
        $conv = Conversation::findOrFail($id);
        $conv->update(['assigned_employee_id' => $request->input('employee_id')]);
        AuditLog::record('employee', 'assign', 'conversation', $conv->id, ['to' => $request->input('employee_id')], $request->user()?->id);
        return response()->json([
            'ok' => true,
            'assigned' => $conv->fresh()->assignedEmployee?->only(['id', 'name']),
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['last_read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markUnread(Request $request, int $id)
    {
        $conv = Conversation::findOrFail($id);
        $conv->update(['last_read_at' => null]);
        return response()->json(['ok' => true]);
    }

    public function listNotes(int $id)
    {
        $notes = Note::where('conversation_id', $id)
            ->with('employee:id,name')
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Note $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'pinned' => (bool) $n->pinned,
                'employee' => $n->employee?->only(['id', 'name']),
                'created_at' => $n->created_at,
            ]);
        return response()->json(['notes' => $notes]);
    }

    public function addNote(Request $request, int $id)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
            'pinned' => 'sometimes|boolean',
        ]);
        Conversation::findOrFail($id); // 404 if missing
        $note = Note::create([
            'conversation_id' => $id,
            'employee_id' => $request->user()?->id,
            'body' => $request->input('body'),
            'pinned' => (bool) $request->input('pinned', false),
        ]);
        AuditLog::record('employee', 'note.add', 'conversation', $id, ['note_id' => $note->id], $request->user()?->id);
        return response()->json([
            'ok' => true,
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'pinned' => $note->pinned,
                'employee' => $request->user()?->only(['id', 'name']),
                'created_at' => $note->created_at,
            ],
        ]);
    }

    public function removeNote(Request $request, int $id, int $noteId)
    {
        $deleted = Note::where('conversation_id', $id)->where('id', $noteId)->delete();
        if ($deleted) {
            AuditLog::record('employee', 'note.remove', 'conversation', $id, ['note_id' => $noteId], $request->user()?->id);
        }
        return response()->json(['ok' => (bool) $deleted]);
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
        AuditLog::record('employee', 'status.set', 'conversation', $conv->id, ['status' => $request->input('status')], $request->user()?->id);

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

    /**
     * Try to fetch customer profile (name + photo) via the channel's Graph
     * API. Saves what we got, leaves the rest. Never throws.
     */
    public function fetchProfile(Request $request, int $id)
    {
        $conv = Conversation::with('customer')->findOrFail($id);
        $customer = $conv->customer;
        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'no_customer'], 404);
        }

        $driver = $this->channels->driver($conv->platform);
        if (! method_exists($driver, 'fetchProfile')) {
            return response()->json(['ok' => false, 'error' => 'not_supported_on_'.$conv->platform], 422);
        }

        $result = $driver->fetchProfile($customer->platform_user_id);
        if ($result['ok']) {
            $updates = [];
            if (! empty($result['name'])) {
                $updates['display_name'] = $result['name'];
            }
            if ($updates) {
                $customer->update($updates);
            }
            if (! empty($result['profile_pic'])) {
                $customer->patchMemory(['profile_pic' => $result['profile_pic']]);
            }
            AuditLog::record('employee', 'profile.fetch', 'customer', $customer->id, ['name' => $result['name'] ?? null], $request->user()?->id);
        }

        return response()->json([
            'ok' => $result['ok'],
            'error' => $result['error'],
            'customer' => array_merge($customer->fresh()->toArray(), [
                'profile_pic' => $customer->fresh()->profile_json['profile_pic'] ?? null,
            ]),
        ]);
    }

    /** Inline edit Customer profile (name + phone). */
    public function updateCustomer(Request $request, int $id)
    {
        $request->validate([
            'display_name' => 'nullable|string|max:191',
            'phone' => 'nullable|string|max:32',
        ]);
        $conv = Conversation::with('customer')->findOrFail($id);
        $customer = $conv->customer;
        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'no_customer'], 404);
        }
        $updates = [];
        if ($request->has('display_name')) $updates['display_name'] = $request->input('display_name');
        if ($request->has('phone')) $updates['phone'] = $request->input('phone');
        if ($updates) {
            $customer->update($updates);
            AuditLog::record('employee', 'customer.update', 'customer', $customer->id, $updates, $request->user()?->id);
        }
        return response()->json([
            'ok' => true,
            'customer' => $customer->fresh()->only(['id', 'display_name', 'platform_user_id', 'phone']),
        ]);
    }

    /** Employees for the assign dropdown. */
    public function employees()
    {
        return response()->json([
            'data' => Employee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'email']),
        ]);
    }
}
