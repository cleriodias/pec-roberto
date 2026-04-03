<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\OnlineUser;
use App\Models\User;
use App\Support\ManagementScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnlineController extends Controller
{
    private const ROLE_LABELS = [
        0 => 'MASTER',
        1 => 'GERENTE',
        2 => 'SUB-GERENTE',
        3 => 'CAIXA',
        4 => 'LANCHONETE',
        5 => 'FUNCIONARIO',
        6 => 'CLIENTE',
    ];

    private const ONLINE_WINDOW_MINUTES = 2;
    private const PRESENCE_RETENTION_MINUTES = 30;
    private const MESSAGE_LIMIT = 120;

    public function index(Request $request): Response
    {
        $user = $this->ensureCanAccessOnline($request->user());
        $this->touchPresence($request, $user);

        return Inertia::render('Online/Index', $this->buildSnapshotPayload($request));
    }

    public function snapshot(Request $request): JsonResponse
    {
        $user = $this->ensureCanAccessOnline($request->user());
        $this->touchPresence($request, $user);

        return response()->json(
            $this->buildSnapshotPayload(
                $request,
                $request->filled('selected_user_id') ? (int) $request->query('selected_user_id') : null
            )
        );
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $this->ensureCanAccessOnline($request->user());
        $presence = $this->touchPresence($request, $user);

        return response()->json([
            'ok' => true,
            'last_seen_at' => optional($presence->last_seen_at)->toIso8601String(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $this->ensureCanAccessOnline($request->user());

        return response()->json($this->buildUnreadSummary((int) $user->id));
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $user = $this->ensureCanAccessOnline($request->user());
        $presence = $this->touchPresence($request, $user);

        $data = $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $recipientId = (int) $data['recipient_user_id'];
        $message = trim((string) $data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Digite uma mensagem antes de enviar.',
            ]);
        }

        $recipient = $this->resolveVisibleContact($request, $user, $recipientId);
        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient_user_id' => 'O usuario selecionado nao esta on-line ou nao pode ser acessado por este perfil.',
            ]);
        }

        ChatMessage::create([
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'sender_role' => (int) $presence->active_role,
            'sender_unit_id' => $presence->active_unit_id,
            'message' => $message,
        ]);

        return response()->json($this->buildSnapshotPayload($request, $recipientId));
    }

    private function buildSnapshotPayload(Request $request, ?int $selectedUserId = null): array
    {
        $user = $this->ensureCanAccessOnline($request->user());
        $this->purgeExpiredPresence();

        $onlineUsers = $this->buildVisibleUsers($request, $user);
        $visibleUserIds = collect($onlineUsers)->pluck('id');

        if (! $visibleUserIds->contains($selectedUserId)) {
            $selectedUserId = $visibleUserIds->first();
        }

        if ($selectedUserId) {
            $this->markConversationAsRead($user->id, (int) $selectedUserId);
        }

        return [
            'onlineUsers' => $onlineUsers,
            'selectedUserId' => $selectedUserId ? (int) $selectedUserId : null,
            'messages' => $selectedUserId
                ? $this->buildConversation((int) $user->id, (int) $selectedUserId)
                : [],
            'unreadSummary' => $this->buildUnreadSummary((int) $user->id),
            'currentUser' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => (int) $user->funcao,
                'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                'unit_id' => $this->resolveActiveUnitId($request),
                'unit_name' => $this->resolveActiveUnitName($request),
            ],
            'presenceWindowSeconds' => self::ONLINE_WINDOW_MINUTES * 60,
        ];
    }

    private function buildVisibleUsers(Request $request, User $viewer): array
    {
        $viewerRole = (int) $viewer->funcao;
        $viewerUnitId = $this->resolveActiveUnitId($request);
        $managedUnitIds = $this->managedUnitIds($viewer);
        $activeSince = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $visiblePresences = OnlineUser::query()
            ->with([
                'user.units:tb2_id,tb2_nome',
                'unit:tb2_id,tb2_nome',
            ])
            ->where('last_seen_at', '>=', $activeSince)
            ->orderByDesc('last_seen_at')
            ->get()
            ->filter(function (?OnlineUser $presence) use ($viewer, $viewerRole, $viewerUnitId, $managedUnitIds) {
                if (! $presence || ! $presence->user) {
                    return false;
                }

                if ((int) $presence->user_id === (int) $viewer->id) {
                    return false;
                }

                return $this->canSeePresence(
                    $viewerRole,
                    $viewerUnitId,
                    $managedUnitIds,
                    (int) $presence->active_role,
                    $presence->active_unit_id ? (int) $presence->active_unit_id : null
                );
            })
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->sortByDesc('last_seen_at')->first())
            ->values();

        $visibleUserIds = $visiblePresences->pluck('user_id')->map(fn ($value) => (int) $value)->all();

        $unreadBySender = empty($visibleUserIds)
            ? collect()
            : ChatMessage::query()
                ->selectRaw('sender_id, COUNT(*) as total')
                ->where('recipient_id', $viewer->id)
                ->whereNull('read_at')
                ->whereIn('sender_id', $visibleUserIds)
                ->groupBy('sender_id')
                ->pluck('total', 'sender_id');

        return $visiblePresences
            ->map(function (OnlineUser $presence) use ($unreadBySender) {
                return [
                    'id' => (int) $presence->user_id,
                    'name' => (string) $presence->user->name,
                    'role' => (int) $presence->active_role,
                    'role_label' => self::ROLE_LABELS[(int) $presence->active_role] ?? '---',
                    'unit_id' => $presence->active_unit_id ? (int) $presence->active_unit_id : null,
                    'unit_name' => $presence->unit?->tb2_nome ?? 'Sem loja ativa',
                    'last_seen_at' => optional($presence->last_seen_at)->toIso8601String(),
                    'unread_count' => (int) ($unreadBySender[(int) $presence->user_id] ?? 0),
                ];
            })
            ->sortBy(fn (array $item) => mb_strtolower($item['name'], 'UTF-8'))
            ->values()
            ->all();
    }

    private function buildUnreadSummary(int $viewerId): array
    {
        $baseQuery = ChatMessage::query()
            ->where('recipient_id', $viewerId)
            ->whereNull('read_at');

        return [
            'unread_total' => (clone $baseQuery)->count(),
            'unread_sender_ids' => (clone $baseQuery)
                ->select('sender_id')
                ->distinct()
                ->pluck('sender_id')
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all(),
        ];
    }

    private function buildConversation(int $viewerId, int $otherUserId): array
    {
        return ChatMessage::query()
            ->with(['sender:id,name'])
            ->where(function ($query) use ($viewerId, $otherUserId) {
                $query->where('sender_id', $viewerId)
                    ->where('recipient_id', $otherUserId);
            })
            ->orWhere(function ($query) use ($viewerId, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                    ->where('recipient_id', $viewerId);
            })
            ->orderByDesc('id')
            ->limit(self::MESSAGE_LIMIT)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => [
                'id' => (int) $message->id,
                'sender_id' => (int) $message->sender_id,
                'recipient_id' => (int) $message->recipient_id,
                'message' => (string) $message->message,
                'sender_name' => (string) ($message->sender?->name ?? '---'),
                'sent_at' => optional($message->created_at)->toIso8601String(),
                'read_at' => optional($message->read_at)->toIso8601String(),
                'sender_role' => (int) $message->sender_role,
                'sender_role_label' => self::ROLE_LABELS[(int) $message->sender_role] ?? '---',
                'is_mine' => (int) $message->sender_id === $viewerId,
            ])
            ->all();
    }

    private function resolveVisibleContact(Request $request, User $viewer, int $recipientId): ?array
    {
        return collect($this->buildVisibleUsers($request, $viewer))
            ->first(fn (array $user) => (int) $user['id'] === $recipientId);
    }

    private function touchPresence(Request $request, User $user): OnlineUser
    {
        $sessionId = (string) $request->session()->getId();

        $presence = OnlineUser::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $user->id,
                'active_role' => (int) $user->funcao,
                'active_unit_id' => $this->resolveActiveUnitId($request),
                'last_seen_at' => now(),
            ]
        );

        $this->purgeExpiredPresence();

        return $presence->fresh(['user', 'unit']) ?? $presence;
    }

    private function purgeExpiredPresence(): void
    {
        OnlineUser::query()
            ->where('last_seen_at', '<', now()->subMinutes(self::PRESENCE_RETENTION_MINUTES))
            ->delete();
    }

    private function markConversationAsRead(int $viewerId, int $otherUserId): void
    {
        ChatMessage::query()
            ->where('sender_id', $otherUserId)
            ->where('recipient_id', $viewerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function canSeePresence(
        int $viewerRole,
        ?int $viewerUnitId,
        Collection $managedUnitIds,
        int $targetRole,
        ?int $targetUnitId
    ): bool {
        if (in_array($targetRole, [5, 6], true)) {
            return false;
        }

        if ($viewerRole === 0) {
            return true;
        }

        if ($viewerRole === 1) {
            return $targetUnitId !== null && $managedUnitIds->contains($targetUnitId);
        }

        if (in_array($viewerRole, [2, 3], true)) {
            return $targetRole === 0
                || $targetRole === 1
                || ($viewerUnitId !== null && $targetUnitId === $viewerUnitId);
        }

        if ($viewerRole === 4) {
            return $viewerUnitId !== null
                && $targetUnitId === $viewerUnitId
                && in_array($targetRole, [2, 3], true);
        }

        return false;
    }

    private function managedUnitIds(User $user): Collection
    {
        return ManagementScope::managedUnitIds($user);
    }

    private function resolveActiveUnitId(Request $request): ?int
    {
        $unit = $request->session()->get('active_unit');
        $unitId = is_array($unit)
            ? ($unit['id'] ?? $unit['tb2_id'] ?? null)
            : (is_object($unit) ? ($unit->id ?? $unit->tb2_id ?? null) : null);

        return $unitId ? (int) $unitId : null;
    }

    private function resolveActiveUnitName(Request $request): ?string
    {
        $unit = $request->session()->get('active_unit');

        return is_array($unit)
            ? ($unit['name'] ?? $unit['tb2_nome'] ?? null)
            : (is_object($unit) ? ($unit->name ?? $unit->tb2_nome ?? null) : null);
    }

    private function ensureCanAccessOnline(?User $user): User
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1, 2, 3, 4], true)) {
            abort(403);
        }

        return $user;
    }
}
