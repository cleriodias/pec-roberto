import AlertMessage from '@/Components/Alert/AlertMessage';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/Button/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    formatRoleBadgeLabel,
    formatUnitBadgeLabel,
    getRoleBadgeStyle,
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

        return `<span style="color:${normalized}">${content}</span>`;
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

const resolveDraftKey = (userId) => String(userId ?? '');

const COMPACT_BADGE_CLASSNAME =
    'inline-flex shrink-0 items-center justify-center rounded-full border font-semibold uppercase tracking-wide whitespace-nowrap';

const ANYDESCK_TYPE_OPTIONS = [
    { label: 'Maquina do Caixa', value: 'Caixa' },
    { label: 'Maquina da Lanchonete', value: 'Lanchonete' },
];

const ANYDESCK_ROLE_TYPE_MAP = {
    3: 'Caixa',
    4: 'Lanchonete',
};

const applyAnyDeskCodeMask = (value) => {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 10);
    const groups = [];

    if (digits.length > 0) {
        groups.push(digits.slice(0, 1));
    }

    if (digits.length > 1) {
        groups.push(digits.slice(1, 4));
    }

    if (digits.length > 4) {
        groups.push(digits.slice(4, 7));
    }

    if (digits.length > 7) {
        groups.push(digits.slice(7, 10));
    }

    return groups.join(' ');
};

export default function OnlineIndex({
    onlineUsers: initialOnlineUsers = [],
    offlineUsers: initialOfflineUsers = [],
    selectedUserId: initialSelectedUserId = null,
    messages: initialMessages = [],
    currentUser = null,
}) {
    const { flash } = usePage().props;
    const [onlineUsers, setOnlineUsers] = useState(initialOnlineUsers);
    const [offlineUsers, setOfflineUsers] = useState(initialOfflineUsers);
    const [selectedUserId, setSelectedUserId] = useState(initialSelectedUserId);
    const [messages, setMessages] = useState(initialMessages);
    const [currentViewer, setCurrentViewer] = useState(currentUser);
    const [draftMessages, setDraftMessages] = useState({});
    const [loadingSnapshot, setLoadingSnapshot] = useState(false);
    const [refreshingSnapshot, setRefreshingSnapshot] = useState(false);
    const [sendingMessage, setSendingMessage] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [editingMessageId, setEditingMessageId] = useState(null);
    const [editDraftMessage, setEditDraftMessage] = useState('');
    const [savingEditMessage, setSavingEditMessage] = useState(false);
    const [editErrorMessage, setEditErrorMessage] = useState('');
    const [showAnyDeskModal, setShowAnyDeskModal] = useState(false);
    const [selectedAnyDeskType, setSelectedAnyDeskType] = useState('');
    const [anyDeskData, setAnyDeskData] = useState(null);
    const [anyDeskCodeDraft, setAnyDeskCodeDraft] = useState('');
    const [loadingAnyDesk, setLoadingAnyDesk] = useState(false);
    const [savingAnyDesk, setSavingAnyDesk] = useState(false);
    const [sendingAnyDesk, setSendingAnyDesk] = useState(false);
    const [anyDeskErrorMessage, setAnyDeskErrorMessage] = useState('');
    const textareaRef = useRef(null);
    const editTextareaRef = useRef(null);
    const messagesEndRef = useRef(null);
    const messagesContainerRef = useRef(null);
    const selectedUserIdRef = useRef(initialSelectedUserId);
    const lastMessageMetaRef = useRef({
        selectedUserId: initialSelectedUserId,
        count: initialMessages.length,
    });

    const selectedUser = useMemo(
        () =>
            [...onlineUsers, ...offlineUsers].find((user) => Number(user.id) === Number(selectedUserId)) ??
            onlineUsers[0] ??
            offlineUsers[0] ??
            null,
        [onlineUsers, offlineUsers, selectedUserId],
    );

    const editingMessage = useMemo(
        () =>
            messages.find((message) => Number(message.id) === Number(editingMessageId)) ?? null,
        [messages, editingMessageId],
    );

    const draftMessage = useMemo(
        () => draftMessages[resolveDraftKey(selectedUserId)] ?? '',
        [draftMessages, selectedUserId],
    );

    const fixedAnyDeskType = useMemo(
        () => ANYDESCK_ROLE_TYPE_MAP[Number(currentViewer?.role ?? -1)] ?? '',
        [currentViewer],
    );

    const anyDeskNeedsTypeSelection = !fixedAnyDeskType;

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

    useEffect(() => {
        if (editingMessageId) {
            window.requestAnimationFrame(() => {
                editTextareaRef.current?.focus();
            });
        }
    }, [editingMessageId]);

    const applySnapshot = (payload, requestedUserId = null) => {
        const nextUsers = Array.isArray(payload?.onlineUsers) ? payload.onlineUsers : [];
        const nextOfflineUsers = Array.isArray(payload?.offlineUsers) ? payload.offlineUsers : [];
        const availableIds = nextUsers
            .map((user) => Number(user.id))
            .concat(nextOfflineUsers.map((user) => Number(user.id)));
        const nextSelectedUserId =
            requestedUserId && availableIds.includes(Number(requestedUserId))
                ? Number(requestedUserId)
                : payload?.selectedUserId && availableIds.includes(Number(payload.selectedUserId))
                  ? Number(payload.selectedUserId)
                  : nextUsers[0]?.id ?? nextOfflineUsers[0]?.id ?? null;

        setOnlineUsers(nextUsers);
        setOfflineUsers(nextOfflineUsers);
        setSelectedUserId(nextSelectedUserId);
        setMessages(Array.isArray(payload?.messages) ? payload.messages : []);
        if (payload?.currentUser) {
            setCurrentViewer(payload.currentUser);
        }
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
            setDraftMessages((current) => {
                const draftKey = resolveDraftKey(selectedUserId);
                const currentMessage = current[draftKey] ?? '';

                return {
                    ...current,
                    [draftKey]: `${currentMessage}${prefix}${suffix}`,
                };
            });
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

        setDraftMessages((current) => ({
            ...current,
            [resolveDraftKey(selectedUserId)]: nextValue,
        }));

        window.requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(start + prefix.length, end + prefix.length);
        });
    };

    const handleSelectUser = (userId) => {
        setSelectedUserId(userId);
        loadSnapshot(userId);
    };

    const closeAnyDeskModal = () => {
        if (savingAnyDesk || sendingAnyDesk) {
            return;
        }

        setShowAnyDeskModal(false);
        setSelectedAnyDeskType(fixedAnyDeskType || '');
        setAnyDeskData(null);
        setAnyDeskCodeDraft('');
        setAnyDeskErrorMessage('');
    };

    const loadAnyDesk = async (type) => {
        if (!type) {
            setAnyDeskData(null);
            setAnyDeskCodeDraft('');
            return;
        }

        setLoadingAnyDesk(true);

        try {
            const response = await axios.get(route('online.anydesck.show'), {
                params: { type },
            });
            const payload = response.data ?? null;

            setAnyDeskData(payload);
            setAnyDeskCodeDraft(applyAnyDeskCodeMask(payload?.code ?? ''));
            setAnyDeskErrorMessage('');
        } catch (error) {
            setAnyDeskData(null);
            setAnyDeskCodeDraft('');
            setAnyDeskErrorMessage(resolveErrorMessage(error, 'Nao foi possivel carregar o codigo AnyDesk.'));
        } finally {
            setLoadingAnyDesk(false);
        }
    };

    const openAnyDeskModal = async () => {
        const nextType = fixedAnyDeskType || '';

        setShowAnyDeskModal(true);
        setSelectedAnyDeskType(nextType);
        setAnyDeskData(null);
        setAnyDeskCodeDraft('');
        setAnyDeskErrorMessage('');

        if (nextType) {
            await loadAnyDesk(nextType);
        }
    };

    const handleSelectAnyDeskType = async (type) => {
        setSelectedAnyDeskType(type);
        setAnyDeskData(null);
        setAnyDeskCodeDraft('');
        setAnyDeskErrorMessage('');
        await loadAnyDesk(type);
    };

    const persistAnyDesk = async (type, code) => {
        const response = await axios.put(route('online.anydesck.update'), {
            type,
            code,
        });
        const payload = response.data ?? null;

        setAnyDeskData(payload);
        setAnyDeskCodeDraft(applyAnyDeskCodeMask(payload?.code ?? code));

        return payload;
    };

    const handleSaveAnyDesk = async (event) => {
        event.preventDefault();

        if (!selectedAnyDeskType) {
            setAnyDeskErrorMessage('Selecione se voce esta na maquina do Caixa ou da Lanchonete.');
            return;
        }

        if (!anyDeskCodeDraft.trim()) {
            setAnyDeskErrorMessage('Informe o codigo AnyDesk antes de salvar.');
            return;
        }

        setSavingAnyDesk(true);

        try {
            await persistAnyDesk(selectedAnyDeskType, anyDeskCodeDraft);
            setAnyDeskErrorMessage('');
        } catch (error) {
            setAnyDeskErrorMessage(resolveErrorMessage(error, 'Nao foi possivel atualizar o codigo AnyDesk.'));
        } finally {
            setSavingAnyDesk(false);
        }
    };

    const handleSendAnyDesk = async () => {
        if (!selectedUser) {
            setAnyDeskErrorMessage('Selecione um usuario no bate-papo antes de enviar o AnyDesk.');
            return;
        }

        if (!selectedAnyDeskType) {
            setAnyDeskErrorMessage('Selecione se voce esta na maquina do Caixa ou da Lanchonete.');
            return;
        }

        if (!anyDeskCodeDraft.trim()) {
            setAnyDeskErrorMessage('Informe o codigo AnyDesk antes de enviar.');
            return;
        }

        setSendingAnyDesk(true);

        try {
            const payload = await persistAnyDesk(selectedAnyDeskType, anyDeskCodeDraft);
            const message = `Codigo AnyDesk (${payload?.type ?? selectedAnyDeskType} - ${payload?.unit_name ?? currentViewer?.unit_name ?? 'Sem loja'}): ${payload?.code ?? anyDeskCodeDraft}`;
            const response = await axios.post(route('online.messages.store'), {
                recipient_user_id: selectedUser.id,
                message,
            });

            applySnapshot(response.data ?? {}, selectedUser.id);
            setAnyDeskErrorMessage('');
            closeAnyDeskModal();
        } catch (error) {
            setAnyDeskErrorMessage(resolveErrorMessage(error, 'Nao foi possivel enviar o codigo AnyDesk pelo bate-papo.'));
        } finally {
            setSendingAnyDesk(false);
        }
    };

    const submitMessage = async () => {
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
            setDraftMessages((current) => ({
                ...current,
                [resolveDraftKey(selectedUser.id)]: '',
            }));
            setErrorMessage('');
            textareaRef.current?.focus();
        } catch (error) {
            setErrorMessage(resolveErrorMessage(error, 'Nao foi possivel enviar a mensagem.'));
        } finally {
            setSendingMessage(false);
        }
    };

    const handleSendMessage = async (event) => {
        event.preventDefault();
        await submitMessage();
    };

    const openEditModal = (message) => {
        if (!message?.is_mine) {
            return;
        }

        setEditingMessageId(message.id);
        setEditDraftMessage(String(message.message ?? ''));
        setEditErrorMessage('');
    };

    const closeEditModal = () => {
        if (savingEditMessage) {
            return;
        }

        setEditingMessageId(null);
        setEditDraftMessage('');
        setEditErrorMessage('');
    };

    const handleUpdateMessage = async (event) => {
        event.preventDefault();

        if (!editingMessage || savingEditMessage) {
            return;
        }

        const nextMessage = editDraftMessage.trim();
        if (!nextMessage) {
            setEditErrorMessage('Digite uma mensagem antes de salvar.');
            return;
        }

        setSavingEditMessage(true);

        try {
            const response = await axios.put(route('online.messages.update', editingMessage.id), {
                message: nextMessage,
            });

            applySnapshot(response.data ?? {}, selectedUserIdRef.current);
            setEditingMessageId(null);
            setEditDraftMessage('');
            setEditErrorMessage('');
            setErrorMessage('');
        } catch (error) {
            setEditErrorMessage(resolveErrorMessage(error, 'Nao foi possivel atualizar a mensagem.'));
        } finally {
            setSavingEditMessage(false);
        }
    };

    const renderContactButton = (user, offline = false) => {
        const isSelected = Number(user.id) === Number(selectedUser?.id);
        const hasUnread = Number(user.unread_count ?? 0) > 0;

        return (
            <button
                type="button"
                key={`${offline ? 'offline' : 'online'}-${user.id}`}
                onClick={() => handleSelectUser(user.id)}
                className={`flex w-full items-center gap-2 overflow-hidden border-b border-gray-100 px-4 py-4 text-left transition last:border-b-0 dark:border-gray-800 ${
                    isSelected
                        ? hasUnread
                            ? 'border-l-4 border-l-emerald-500 bg-slate-100 dark:border-l-emerald-400 dark:bg-slate-800/80'
                            : 'bg-slate-100 dark:bg-slate-800/80'
                        : hasUnread
                          ? 'border-l-4 border-l-emerald-500 bg-emerald-50/80 hover:bg-emerald-100/70 dark:border-l-emerald-400 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/15'
                          : offline
                            ? 'bg-gray-50/80 hover:bg-gray-100 dark:bg-gray-900/40 dark:hover:bg-gray-800/60'
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
                    className={COMPACT_BADGE_CLASSNAME}
                    style={{
                        ...getRoleBadgeStyle(user.role_label),
                        padding: '0 6px',
                        fontSize: '9px',
                        lineHeight: '12px',
                        minHeight: '16px',
                    }}
                >
                    {formatRoleBadgeLabel(user.role_label)}
                </span>
                <span
                    className={COMPACT_BADGE_CLASSNAME}
                    style={{
                        ...getUnitBadgeStyle(user.unit_name),
                        padding: '0 6px',
                        fontSize: '9px',
                        lineHeight: '12px',
                        minHeight: '16px',
                        minWidth: '52px',
                    }}
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
    };

    return (
        <AuthenticatedLayout
            header={(
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Usuarios On-Line</h2>
                </div>
            )}
        >
            <Head title="On-Line" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    {errorMessage && (
                        <div className="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            {errorMessage}
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
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => loadSnapshot(selectedUserIdRef.current)}
                                        disabled={loadingSnapshot || refreshingSnapshot}
                                        className="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-70 dark:border-gray-600 dark:text-gray-200"
                                    >
                                        {loadingSnapshot || refreshingSnapshot ? 'Atualizando...' : 'Atualizar'}
                                    </button>
                                </div>
                            </div>

                            <div className="max-h-[68vh] overflow-y-auto">
                                {onlineUsers.length === 0 && offlineUsers.length === 0 ? (
                                    <div className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                                        Nenhum usuario visivel neste momento.
                                    </div>
                                ) : (
                                    <>
                                        {onlineUsers.length > 0 && onlineUsers.map((user) => renderContactButton(user))}

                                        {offlineUsers.length > 0 && (
                                            <div className="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                    Offline
                                                </p>
                                            </div>
                                        )}

                                        {offlineUsers.length > 0 && offlineUsers.map((user) => renderContactButton(user, true))}
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div className="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                                {selectedUser ? (
                                    <div className="flex flex-col gap-1">
                                        <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                            {selectedUser.name}
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            {selectedUser.role_label} | Loja: {selectedUser.unit_name ?? '---'}
                                        </p>
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
                                                        onClick={message.is_mine ? () => openEditModal(message) : undefined}
                                                        className={`max-w-[85%] rounded-2xl px-4 py-3 shadow-sm ${
                                                            message.is_mine
                                                                ? 'cursor-pointer bg-slate-300 text-slate-800 transition hover:bg-slate-400 dark:bg-slate-600 dark:text-slate-100 dark:hover:bg-slate-500'
                                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-100'
                                                        }`}
                                                        title={message.is_mine ? 'Clique para editar sua mensagem' : undefined}
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
                                            onChange={(event) =>
                                                setDraftMessages((current) => ({
                                                    ...current,
                                                    [resolveDraftKey(selectedUserId)]: event.target.value,
                                                }))
                                            }
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
                                                `Enter` quebra linha. Use o botao para enviar. Clique na sua mensagem para editar.
                                            </p>
                                            <div className="flex items-center gap-2 self-end">
                                                <PrimaryButton
                                                    type="button"
                                                    onClick={openAnyDeskModal}
                                                    disabled={loadingAnyDesk || savingAnyDesk || sendingAnyDesk}
                                                    className="rounded-2xl px-5 py-2 normal-case tracking-normal"
                                                >
                                                    AnyDesk
                                                </PrimaryButton>
                                                <PrimaryButton
                                                    type="submit"
                                                    disabled={!selectedUser || sendingMessage}
                                                    className="rounded-2xl px-5 py-2 normal-case tracking-normal"
                                                >
                                                    {sendingMessage ? 'Enviando...' : 'Enviar'}
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={Boolean(editingMessage)} onClose={closeEditModal} maxWidth="xl" tone="light">
                <form onSubmit={handleUpdateMessage} className="space-y-4 p-6">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Editar mensagem</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Ajuste o texto e salve quando terminar.
                        </p>
                    </div>

                    {editErrorMessage && (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {editErrorMessage}
                        </div>
                    )}

                    <textarea
                        ref={editTextareaRef}
                        rows={6}
                        value={editDraftMessage}
                        onChange={(event) => setEditDraftMessage(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.stopPropagation();
                            }
                        }}
                        className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    />

                    <div className="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={savingEditMessage}
                            className="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={savingEditMessage}
                            className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {savingEditMessage ? 'Salvando...' : 'Salvar'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={showAnyDeskModal} onClose={closeAnyDeskModal} maxWidth="lg" tone="light">
                <form onSubmit={handleSaveAnyDesk} className="space-y-4 p-6">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">AnyDesk</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Consulte, atualize e envie o codigo da maquina da sua loja ativa.
                        </p>
                    </div>

                    {anyDeskNeedsTypeSelection && (
                        <div className="space-y-2">
                            <p className="text-sm font-semibold text-gray-700">Onde voce esta?</p>
                            <div className="flex flex-wrap gap-2">
                                {ANYDESCK_TYPE_OPTIONS.map((option) => {
                                    const isActive = selectedAnyDeskType === option.value;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => handleSelectAnyDeskType(option.value)}
                                            className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${
                                                isActive
                                                    ? 'border-blue-500 bg-blue-500 text-white'
                                                    : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400 hover:text-blue-600'
                                            }`}
                                        >
                                            {option.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <div className="grid gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 sm:grid-cols-3">
                        <div>
                            <p className="text-xs font-semibold uppercase text-gray-500">Perfil</p>
                            <p className="mt-1 font-semibold">{anyDeskData?.role_label ?? currentViewer?.role_label ?? '---'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase text-gray-500">Loja</p>
                            <p className="mt-1 font-semibold">{anyDeskData?.unit_name ?? currentViewer?.unit_name ?? '---'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase text-gray-500">Tipo</p>
                            <p className="mt-1 font-semibold">{selectedAnyDeskType || 'Selecione acima'}</p>
                        </div>
                    </div>

                    {anyDeskErrorMessage && (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {anyDeskErrorMessage}
                        </div>
                    )}

                    <div>
                        <label htmlFor="online-anydesk-code" className="text-sm font-semibold text-gray-700">
                            Codigo AnyDesk
                        </label>
                        <input
                            id="online-anydesk-code"
                            type="text"
                            value={anyDeskCodeDraft}
                            onChange={(event) => setAnyDeskCodeDraft(applyAnyDeskCodeMask(event.target.value))}
                            placeholder="1 186 429 402"
                            inputMode="numeric"
                            disabled={loadingAnyDesk || !selectedAnyDeskType}
                            className="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-gray-100"
                        />
                        <p className="mt-2 text-xs text-gray-500">
                            {loadingAnyDesk
                                ? 'Carregando codigo da loja...'
                                : anyDeskData?.code
                                  ? 'Codigo carregado. Voce pode atualizar se necessario.'
                                  : 'Nenhum codigo cadastrado para esta loja/tipo. Informe e salve para criar.'}
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-gray-500">
                            {selectedUser
                                ? `O codigo sera enviado para ${selectedUser.name}.`
                                : 'Selecione um usuario no bate-papo para habilitar o envio.'}
                        </p>
                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={closeAnyDeskModal}
                                disabled={savingAnyDesk || sendingAnyDesk}
                                className="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Fechar
                            </button>
                            <PrimaryButton
                                type="submit"
                                disabled={!selectedAnyDeskType || loadingAnyDesk || savingAnyDesk || sendingAnyDesk}
                                className="rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                            >
                                {savingAnyDesk ? 'Salvando...' : 'Atualizar dados'}
                            </PrimaryButton>
                            <PrimaryButton
                                type="button"
                                onClick={handleSendAnyDesk}
                                disabled={!selectedUser || !selectedAnyDeskType || loadingAnyDesk || savingAnyDesk || sendingAnyDesk}
                                className="rounded-full px-4 py-2 text-sm normal-case tracking-normal"
                            >
                                {sendingAnyDesk ? 'Enviando...' : 'Enviar no bate-papo'}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
