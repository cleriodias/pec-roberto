import AlertMessage from '@/Components/Alert/AlertMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    formatRoleBadgeLabel,
    formatUnitBadgeLabel,
    getRoleBadgeClassName,
    getRoleBadgeStyle,
    getUnitBadgeClassName,
    getUnitBadgeStyle,
    getUserNameBadgeClassName,
} from '@/Utils/brandBadges';
import { formatBrazilDateTime } from '@/Utils/date';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';

const COLOR_OPTIONS = [
    { label: 'Azul', value: '#2563eb' },
    { label: 'Verde', value: '#059669' },
    { label: 'Vermelho', value: '#dc2626' },
    { label: 'Laranja', value: '#ea580c' },
    { label: 'Roxo', value: '#7c3aed' },
    { label: 'Cinza', value: '#475569' },
];

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const replaceSimpleTag = (value, tag, openTag, closeTag) =>
    value.replace(
        new RegExp(`\\[${tag}\\]([\\s\\S]*?)\\[\\/${tag}\\]`, 'gi'),
        `${openTag}$1${closeTag}`,
    );

const replaceColorTag = (value) =>
    value.replace(/\[color=([#a-zA-Z0-9]+)\]([\s\S]*?)\[\/color\]/gi, (match, color, content) => {
        const normalized = String(color ?? '').trim().toLowerCase();
        const allowed = COLOR_OPTIONS.map((option) => option.value.toLowerCase());

        if (!allowed.includes(normalized)) {
            return content;
        }

        return content;
    });

const renderMessage = (value) => {
    let formatted = escapeHtml(value);

    for (let index = 0; index < 4; index += 1) {
        const previous = formatted;
        formatted = replaceSimpleTag(formatted, 'b', '<strong>', '</strong>');
        formatted = replaceSimpleTag(formatted, 'i', '<em>', '</em>');
        formatted = replaceSimpleTag(formatted, 'u', '<u>', '</u>');
        formatted = replaceColorTag(formatted);

        if (formatted === previous) {
            break;
        }
    }

    return formatted.replace(/\r?\n/g, '<br />');
};

const resolveErrorMessage = (error, fallback) => {
    if (error?.response?.data?.errors) {
        const first = Object.values(error.response.data.errors).flat()[0];
        if (first) {
            return String(first);
        }
    }

    if (error?.response?.data?.message) {
        return String(error.response.data.message);
    }

    if (error?.message) {
        return String(error.message);
    }

    return fallback;
};

export default function OnlineIndex({
    onlineUsers: initialOnlineUsers = [],
    selectedUserId: initialSelectedUserId = null,
    messages: initialMessages = [],
    currentUser = null,
}) {
    const { flash } = usePage().props;
    const [onlineUsers, setOnlineUsers] = useState(initialOnlineUsers);
    const [selectedUserId, setSelectedUserId] = useState(initialSelectedUserId);
    const [messages, setMessages] = useState(initialMessages);
    const [draftMessage, setDraftMessage] = useState('');
    const [loadingSnapshot, setLoadingSnapshot] = useState(false);
    const [refreshingSnapshot, setRefreshingSnapshot] = useState(false);
    const [sendingMessage, setSendingMessage] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [lastSyncAt, setLastSyncAt] = useState(() => new Date().toISOString());
    const textareaRef = useRef(null);
    const messagesEndRef = useRef(null);
    const messagesContainerRef = useRef(null);
    const selectedUserIdRef = useRef(initialSelectedUserId);
    const lastMessageMetaRef = useRef({
        selectedUserId: initialSelectedUserId,
        count: initialMessages.length,
    });

    const selectedUser = useMemo(
        () =>
            onlineUsers.find((user) => Number(user.id) === Number(selectedUserId)) ??
            onlineUsers[0] ??
            null,
        [onlineUsers, selectedUserId],
    );

    useEffect(() => {
        selectedUserIdRef.current = selectedUserId;
    }, [selectedUserId]);

    useEffect(() => {
        const container = messagesContainerRef.current;
        const previous = lastMessageMetaRef.current;
        const latestMessage = messages[messages.length - 1] ?? null;
        const selectedChanged =
            Number(previous.selectedUserId ?? 0) !== Number(selectedUser?.id ?? 0);
        const countIncreased = messages.length > Number(previous.count ?? 0);
        const distanceFromBottom = container
            ? container.scrollHeight - container.scrollTop - container.clientHeight
            : 0;
        const shouldStickToBottom =
            selectedChanged ||
            (countIncreased && (distanceFromBottom < 120 || Boolean(latestMessage?.is_mine)));

        if (shouldStickToBottom) {
            messagesEndRef.current?.scrollIntoView({
                behavior: selectedChanged ? 'auto' : 'smooth',
            });
        }

        lastMessageMetaRef.current = {
            selectedUserId: selectedUser?.id ?? null,
            count: messages.length,
        };
    }, [messages, selectedUser?.id]);

    useEffect(() => {
        if (selectedUser) {
            textareaRef.current?.focus();
        }
        setErrorMessage('');
    }, [selectedUser?.id]);

    const applySnapshot = (payload, requestedUserId = null) => {
        const nextUsers = Array.isArray(payload?.onlineUsers) ? payload.onlineUsers : [];
        const availableIds = nextUsers.map((user) => Number(user.id));
        const nextSelectedUserId =
            requestedUserId && availableIds.includes(Number(requestedUserId))
                ? Number(requestedUserId)
                : payload?.selectedUserId && availableIds.includes(Number(payload.selectedUserId))
                  ? Number(payload.selectedUserId)
                  : nextUsers[0]?.id ?? null;

        setOnlineUsers(nextUsers);
        setSelectedUserId(nextSelectedUserId);
        setMessages(Array.isArray(payload?.messages) ? payload.messages : []);
        setLastSyncAt(new Date().toISOString());
    };

    const loadSnapshot = async (requestedUserId = null, silent = false) => {
        if (!silent) {
            setLoadingSnapshot(true);
        } else {
            setRefreshingSnapshot(true);
        }

        try {
            const response = await axios.get(route('online.snapshot'), {
                params: requestedUserId ? { selected_user_id: requestedUserId } : {},
            });

            applySnapshot(response.data ?? {}, requestedUserId);
            setErrorMessage('');
        } catch (error) {
            if (!silent) {
                setErrorMessage(resolveErrorMessage(error, 'Nao foi possivel atualizar a lista de usuarios on-line.'));
            }
        } finally {
            if (!silent) {
                setLoadingSnapshot(false);
            } else {
                setRefreshingSnapshot(false);
            }
        }
    };

    useEffect(() => {
        const intervalId = window.setInterval(() => {
            loadSnapshot(selectedUserIdRef.current, true);
        }, 15000);

        return () => {
            window.clearInterval(intervalId);
        };
    }, []);

    const wrapSelection = (prefix, suffix = prefix) => {
        const textarea = textareaRef.current;
        if (!textarea) {
            setDraftMessage((current) => `${current}${prefix}${suffix}`);
            return;
        }

        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? 0;
        const selected = draftMessage.slice(start, end);
        const nextValue =
            draftMessage.slice(0, start) +
            prefix +
            selected +
            suffix +
            draftMessage.slice(end);

        setDraftMessage(nextValue);

        window.requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(start + prefix.length, end + prefix.length);
        });
    };

    const handleSelectUser = (userId) => {
        setSelectedUserId(userId);
        loadSnapshot(userId);
    };

    const handleSendMessage = async (event) => {
        event.preventDefault();

        if (!selectedUser || sendingMessage) {
            return;
        }

        const message = draftMessage.trim();
        if (!message) {
            setErrorMessage('Digite uma mensagem antes de enviar.');
            return;
        }

        setSendingMessage(true);

        try {
            const response = await axios.post(route('online.messages.store'), {
                recipient_user_id: selectedUser.id,
                message,
            });

            applySnapshot(response.data ?? {}, selectedUser.id);
            setDraftMessage('');
            setErrorMessage('');
            textareaRef.current?.focus();
        } catch (error) {
            setErrorMessage(resolveErrorMessage(error, 'Nao foi possivel enviar a mensagem.'));
        } finally {
            setSendingMessage(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={(
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Usuarios On-Line</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Lista apenas usuarios ativos agora, com funcao e loja da sessao atual.
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Seu perfil atual: <span className="font-semibold text-gray-700 dark:text-gray-100">{currentUser?.role_label ?? '---'}</span>
                        {' '}| Loja ativa: <span className="font-semibold text-gray-700 dark:text-gray-100">{currentUser?.unit_name ?? '---'}</span>
                    </p>
                </div>
            )}
        >
            <Head title="On-Line" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    {(errorMessage || loadingSnapshot) && (
                        <div className="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            {loadingSnapshot ? 'Atualizando usuarios on-line...' : errorMessage}
                        </div>
                    )}

                    <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div className="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">On-Line</p>
                                        <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                            {onlineUsers.length} usuario(s)
                                        </h3>
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Atualizacao automatica a cada 15 segundos.
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {refreshingSnapshot && (
                                            <span className="text-[11px] font-semibold uppercase text-emerald-600 dark:text-emerald-300">
                                                Sincronizando
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => loadSnapshot(selectedUserIdRef.current)}
                                            className="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            Atualizar
                                        </button>
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Ultima sincronizacao: {formatBrazilDateTime(lastSyncAt)}
                                </p>
                            </div>

                            <div className="max-h-[68vh] overflow-y-auto">
                                {onlineUsers.length === 0 ? (
                                    <div className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                                        Nenhum usuario visivel on-line neste momento.
                                    </div>
                                ) : (
                                    onlineUsers.map((user) => {
                                        const isSelected = Number(user.id) === Number(selectedUser?.id);
                                        const hasUnread = Number(user.unread_count ?? 0) > 0;

                                        return (
                                            <button
                                                type="button"
                                                key={user.id}
                                                onClick={() => handleSelectUser(user.id)}
                                                className={`flex w-full items-center gap-2 overflow-hidden border-b border-gray-100 px-4 py-4 text-left transition last:border-b-0 dark:border-gray-800 ${
                                                    isSelected
                                                        ? hasUnread
                                                            ? 'border-l-4 border-l-emerald-500 bg-slate-100 dark:border-l-emerald-400 dark:bg-slate-800/80'
                                                            : 'bg-slate-100 dark:bg-slate-800/80'
                                                        : hasUnread
                                                          ? 'border-l-4 border-l-emerald-500 bg-emerald-50/80 hover:bg-emerald-100/70 dark:border-l-emerald-400 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/15'
                                                          : 'hover:bg-gray-50 dark:hover:bg-gray-800/70'
                                                }`}
                                            >
                                                <span
                                                    className={`inline-flex min-w-[72px] max-w-[108px] items-center justify-center ${getUserNameBadgeClassName()}`}
                                                >
                                                    <span className="truncate">
                                                        {String(user.name ?? '').toUpperCase()}
                                                    </span>
                                                </span>
                                                <span
                                                    className={`inline-flex shrink-0 items-center justify-center uppercase tracking-wide ${getRoleBadgeClassName()}`}
                                                    style={getRoleBadgeStyle(user.role_label)}
                                                >
                                                    {formatRoleBadgeLabel(user.role_label)}
                                                </span>
                                                <span
                                                    className={`inline-flex min-w-[88px] shrink-0 items-center justify-center uppercase tracking-wide ${getUnitBadgeClassName()}`}
                                                    style={getUnitBadgeStyle(user.unit_name)}
                                                >
                                                    {formatUnitBadgeLabel(user.unit_name)}
                                                </span>
                                                {hasUnread && (
                                                    <span className="ms-auto -mt-3 shrink-0 rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white shadow-sm">
                                                        {user.unread_count}
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div className="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                                {selectedUser ? (
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex flex-col gap-1">
                                            <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                                Conversa com {selectedUser.name}
                                            </h3>
                                            <p className="text-sm text-gray-500 dark:text-gray-300">
                                                {selectedUser.role_label} | Loja: {selectedUser.unit_name ?? '---'}
                                            </p>
                                        </div>
                                        <div className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                                            Ao vivo
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                            Conversa
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            Selecione um usuario on-line para iniciar.
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="flex min-h-[68vh] flex-col">
                                <div
                                    ref={messagesContainerRef}
                                    className="flex-1 space-y-3 overflow-y-auto px-5 py-5"
                                >
                                    {selectedUser ? (
                                        messages.length === 0 ? (
                                            <div className="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                Nenhuma mensagem ainda. Use a caixa abaixo para iniciar a conversa.
                                            </div>
                                        ) : (
                                            messages.map((message) => (
                                                <div
                                                    key={message.id}
                                                    className={`flex ${message.is_mine ? 'justify-end' : 'justify-start'}`}
                                                >
                                                    <div
                                                        className={`max-w-[85%] rounded-2xl px-4 py-3 shadow-sm ${
                                                            message.is_mine
                                                                ? 'bg-slate-300 text-slate-800 dark:bg-slate-600 dark:text-slate-100'
                                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-100'
                                                        }`}
                                                    >
                                                        <div
                                                            className={`mb-2 text-[11px] font-semibold uppercase ${
                                                                message.is_mine
                                                                    ? ''
                                                                    : 'text-slate-500 dark:text-slate-400'
                                                            }`}
                                                            style={message.is_mine ? { color: '#475569' } : undefined}
                                                        >
                                                            {String(message.sender_name ?? '---').toUpperCase()} - {message.sender_role_label} | {formatBrazilDateTime(message.sent_at)}
                                                        </div>
                                                        <div
                                                            className="prose prose-sm max-w-none text-inherit prose-p:my-0 prose-strong:text-inherit prose-em:text-inherit prose-u:text-inherit"
                                                            dangerouslySetInnerHTML={{
                                                                __html: renderMessage(message.message),
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))
                                        )
                                    ) : (
                                        <div className="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                            Nenhum usuario visivel on-line para este perfil neste momento.
                                        </div>
                                    )}
                                    <div ref={messagesEndRef}></div>
                                </div>

                                <div className="border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                                    <div className="mb-3 flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => wrapSelection('[b]', '[/b]')}
                                            className="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            Negrito
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => wrapSelection('[i]', '[/i]')}
                                            className="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            Italico
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => wrapSelection('[u]', '[/u]')}
                                            className="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            Sublinhado
                                        </button>
                                        {COLOR_OPTIONS.map((option) => (
                                            <button
                                                type="button"
                                                key={option.value}
                                                onClick={() => wrapSelection(`[color=${option.value}]`, '[/color]')}
                                                className="inline-flex items-center gap-2 rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200"
                                            >
                                                <span
                                                    className="h-3 w-3 rounded-full"
                                                    style={{ backgroundColor: option.value }}
                                                ></span>
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>

                                    <form onSubmit={handleSendMessage} className="space-y-3">
                                        <textarea
                                            ref={textareaRef}
                                            rows={4}
                                            value={draftMessage}
                                            onChange={(event) => setDraftMessage(event.target.value)}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter' && !event.shiftKey) {
                                                    event.preventDefault();
                                                    handleSendMessage(event);
                                                }
                                            }}
                                            placeholder={
                                                selectedUser
                                                    ? 'Digite sua mensagem. As marcacoes simples sao mantidas.'
                                                    : 'Selecione um usuario on-line para conversar.'
                                            }
                                            disabled={!selectedUser || sendingMessage}
                                            className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:disabled:bg-gray-900"
                                        />
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                `Enter` envia, `Shift + Enter` quebra linha. Marcacoes: [b], [i], [u] e [color=#xxxxxx].
                                            </p>
                                            <button
                                                type="submit"
                                                disabled={!selectedUser || sendingMessage}
                                                className="rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {sendingMessage ? 'Enviando...' : 'Enviar'}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
