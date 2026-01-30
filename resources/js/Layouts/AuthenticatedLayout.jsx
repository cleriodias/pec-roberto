import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const MenuLabel = ({ icon, text }) => (
    <span className="inline-flex items-center gap-2">
        <i className={`${icon} text-base`} aria-hidden="true"></i>
        <span>{text}</span>
    </span>
);

const ACCESS_STORAGE_KEY = 'menuAccessConfig';
const ORDER_STORAGE_KEY = 'menuOrderConfig';
const DEFAULT_MENU_KEYS = [
    'dashboard',
    'users',
    'units',
    'products',
    'boletos',
    'cashier_close',
    'reports_control',
    'reports_cash',
    'reports_sales_today',
    'reports_sales_period',
    'reports_sales_detailed',
    'reports_lanchonete',
    'reports_vale',
    'reports_refeicao',
    'reports_adiantamentos',
    'reports_fornecedores',
    'supplier_disputes',
    'reports_gastos',
    'reports_descarte',
    'discard',
    'switch_unit',
    'switch_role',
    'salary_advances',
    'expenses',
    'notices',
    'settings',
    'lanchonete_terminal',
];

export default function AuthenticatedLayout({ header, headerClassName = '', children }) {
    const pageProps = usePage().props;
    const user = pageProps.auth.user;
    const activeUnitName = pageProps.auth.unit?.name ?? 'Dashboard';
    const effectiveRole = user ? Number(user.funcao) : null;
    const originalRole = user ? Number(user.funcao_original ?? user.funcao) : null;
    const isMasterOriginal = originalRole === 0;
    const roleLabels = {
        0: 'MASTER',
        1: 'GERENTE',
        2: 'SUB-GERENTE',
        3: 'CAIXA',
        4: 'LANCHONETE',
        5: 'FUNCIONÁRIO',
        6: 'CLIENTE',
    };
    const isCashier = user && effectiveRole === 3;
    const isLanchonete = user && effectiveRole === 4;
    const isMaster = user && effectiveRole === 0;
    const canSeeUsers = user && ([0, 1].includes(effectiveRole) || isMasterOriginal);
    const canSeeUnits = canSeeUsers;
    const canSeeReports = canSeeUnits;
    const canSeeExpenses = user && (canSeeReports || effectiveRole === 3);
    const canAccessBoletos = user && ([0, 3].includes(effectiveRole) || isMasterOriginal);
    const canSwitchUnit = user && [0, 1].includes(originalRole);
    const canSwitchRole = user && isMasterOriginal;
    const hasLanchoneteRoute =
        typeof route === 'function' && route().has && route().has('lanchonete.terminal');

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [menuAccessConfig, setMenuAccessConfig] = useState(null);
    const [menuOrderConfig, setMenuOrderConfig] = useState(null);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        try {
            const raw = window.localStorage.getItem(ACCESS_STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                const allKeys = new Set();
                Object.values(parsed ?? {}).forEach((value) => {
                    if (Array.isArray(value)) {
                        value.forEach((key) => allKeys.add(key));
                    }
                });
                const missingKeys = DEFAULT_MENU_KEYS.filter((key) => !allKeys.has(key));
                if (missingKeys.length > 0) {
                    const merged = { ...parsed };
                    Object.entries(merged).forEach(([role, value]) => {
                        if (!Array.isArray(value)) {
                            return;
                        }
                        const next = [...value];
                        missingKeys.forEach((key) => {
                            if (!next.includes(key)) {
                                next.push(key);
                            }
                        });
                        merged[role] = next;
                    });
                    window.localStorage.setItem(ACCESS_STORAGE_KEY, JSON.stringify(merged));
                    setMenuAccessConfig(merged);
                } else {
                    setMenuAccessConfig(parsed);
                }
            }
        } catch (err) {
            console.error('Failed to load menuAccessConfig', err);
        }
        try {
            const rawOrder = window.localStorage.getItem(ORDER_STORAGE_KEY);
            if (rawOrder) {
                setMenuOrderConfig(JSON.parse(rawOrder));
            }
        } catch (err) {
            console.error('Failed to load menuOrderConfig', err);
        }
    }, []);

    const hasMenuAccess = useMemo(() => {
        const defaultAllow = new Set(DEFAULT_MENU_KEYS);

        return (key) => {
            if (isMasterOriginal) {
                return true;
            }
            if (!menuAccessConfig || effectiveRole === null) {
                return defaultAllow.has(key);
            }
            const allowed = menuAccessConfig[effectiveRole];
            if (!allowed) {
                return defaultAllow.has(key);
            }
            return Array.isArray(allowed) ? allowed.includes(key) : defaultAllow.has(key);
        };
    }, [menuAccessConfig, effectiveRole]);

    const orderMap = useMemo(() => {
        if (!menuOrderConfig || !Array.isArray(menuOrderConfig)) {
            return {};
        }
        return menuOrderConfig.reduce((acc, key, idx) => {
            acc[key] = idx;
            return acc;
        }, {});
    }, [menuOrderConfig]);

    const sortMenu = (items) =>
        items
            .map((item, idx) => ({
                ...item,
                order: orderMap[item.key] ?? 1000 + idx,
            }))
            .sort((a, b) => a.order - b.order);

    const mainMenuItems = sortMenu(
        [
            {
                key: 'dashboard',
                visible: true,
                node: (
                    <NavLink
                        href={route('dashboard')}
                        active={route().current('dashboard')}
                    >
                        <MenuLabel icon="bi bi-speedometer2" text={activeUnitName} />
                    </NavLink>
                ),
            },
            {
                key: 'products',
                visible: hasMenuAccess('products'),
                node: (
                    <NavLink
                        href={route('products.index')}
                        active={route().current('products.*')}
                    >
                        <MenuLabel icon="bi bi-box-seam" text="Produtos" />
                    </NavLink>
                ),
            },
            {
                key: 'boletos',
                visible: canAccessBoletos && hasMenuAccess('boletos'),
                node: (
                    <NavLink
                        href={route('boletos.index')}
                        active={route().current('boletos.index')}
                    >
                        <MenuLabel icon="bi bi-card-text" text="Boletos" />
                    </NavLink>
                ),
            },
            {
                key: 'expenses',
                visible: canSeeExpenses && hasMenuAccess('expenses'),
                node: (
                    <NavLink
                        href={route('expenses.index')}
                        active={route().current('expenses.*')}
                    >
                        <MenuLabel icon="bi bi-receipt" text="Gastos" />
                    </NavLink>
                ),
            },
            {
                key: 'cashier_close',
                visible: isCashier && hasMenuAccess('cashier_close'),
                node: (
                    <NavLink
                        href={route('cashier.close')}
                        active={route().current('cashier.close')}
                    >
                        <MenuLabel icon="bi bi-cash-stack" text="Fechar CX" />
                    </NavLink>
                ),
            },
            {
                key: 'reports_control',
                visible: canSeeReports && hasMenuAccess('reports_control'),
                node: (
                    <NavLink
                        href={route('reports.control')}
                        active={route().current('reports.control')}
                    >
                        <MenuLabel icon="bi bi-graph-up-arrow" text="Controle" />
                    </NavLink>
                ),
            },
            {
                key: 'reports_cash',
                visible: canSeeReports && hasMenuAccess('reports_cash'),
                node: (
                    <NavLink
                        href={route('reports.cash.closure')}
                        active={route().current('reports.cash.closure')}
                    >
                        <MenuLabel icon="bi bi-clipboard-data" text="Fech. de CAIXA" />
                    </NavLink>
                ),
            },
            {
                key: 'lanchonete_terminal',
                visible: isLanchonete && hasMenuAccess('lanchonete_terminal') && hasLanchoneteRoute,
                node: (
                    <NavLink
                        href={hasLanchoneteRoute ? route('lanchonete.terminal') : '#'}
                        active={hasLanchoneteRoute ? route().current('lanchonete.terminal') : false}
                    >
                        <MenuLabel icon="bi bi-egg-fried" text="Lanchonete" />
                    </NavLink>
                ),
            },
        ].filter((item) => item.visible)
    );

    const dropdownMenuItems = sortMenu(
        [
            {
                key: 'reports_sales_today',
                visible: canSeeReports && hasMenuAccess('reports_sales_today'),
                node: (
                    <Dropdown.Link href={route('reports.sales.today')}>
                        <MenuLabel icon="bi bi-calendar-day" text="Vendas hoje" />
                    </Dropdown.Link>
                ),
            },
            {
                key: 'reports_sales_period',
                visible: canSeeReports && hasMenuAccess('reports_sales_period'),
                node: (
                    <Dropdown.Link href={route('reports.sales.period')}>
                        <MenuLabel icon="bi bi-calendar-range" text="Vendas periodo" />
                    </Dropdown.Link>
                ),
            },
            {
                key: 'reports_sales_detailed',
                visible: canSeeReports && hasMenuAccess('reports_sales_detailed'),
                node: (
                    <Dropdown.Link href={route('reports.sales.detailed')}>
                        <MenuLabel icon="bi bi-card-checklist" text="Detalhado" />
                    </Dropdown.Link>
                ),
            },
            {
                key: 'reports_lanchonete',
                visible: canSeeReports && hasMenuAccess('reports_lanchonete'),
                node: (
                    <Dropdown.Link href={route('reports.lanchonete')}>
                        <MenuLabel icon="bi bi-cup-hot" text="Relatório Lanchonete" />
                    </Dropdown.Link>
                ),
            },
            {
                key: 'settings',
                visible: isMasterOriginal && hasMenuAccess('settings'),
                node: (
                    <Dropdown.Link href={route('settings.config')}>
                        <MenuLabel icon="bi bi-gear" text="Farrammentas" />
                    </Dropdown.Link>
                ),
            },
            {
                key: 'discard',
                visible: hasMenuAccess('discard'),
                node: (
                    <Dropdown.Link href={route('products.discard')}>
                        <MenuLabel icon="bi bi-recycle" text="Descarte" />
                    </Dropdown.Link>
                ),
            },
        ].filter((item) => item.visible)
    );

    const handleLogout = () => {
        router.post(
            route('logout'),
            { _token: pageProps?.csrf_token ?? '' },
            {
                onSuccess: () => {
                    window.location.reload();
                },
            },
        );
    };

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav className="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex items-center">
                                {mainMenuItems.map((item) => (
                                    <span key={item.key}>{item.node}</span>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {canSwitchUnit && (
                                <Link
                                    href={route('reports.switch-unit')}
                                    className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-100"
                                >
                                    <i className="bi bi-arrow-left-right" aria-hidden="true"></i>
                                    Trocar unidade
                                </Link>
                            )}
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2 rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                <span>{user.name}</span>
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                                    {roleLabels[effectiveRole] ?? '---'}
                                                </span>

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>
                                            <MenuLabel icon="bi bi-person-circle" text="Perfil" />
                                        </Dropdown.Link>
                                        {dropdownMenuItems.map((item) => (
                                            <span key={item.key}>{item.node}</span>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={handleLogout}
                                            className="w-full px-4 py-2 text-left text-sm font-semibold text-red-600 transition hover:text-red-700 focus:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/20"
                                        >
                                            <MenuLabel icon="bi bi-box-arrow-right" text="Sair" />
                                        </button>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            showingNavigationDropdown ? 'hidden' : 'inline-flex'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown ? 'inline-flex' : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        {mainMenuItems.map((item) => (
                            <div key={item.key}>{item.node}</div>
                        ))}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                                <span className="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    {roleLabels[effectiveRole] ?? '---'}
                                </span>
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <div>
                                {canSwitchUnit && (
                                    <ResponsiveNavLink
                                        href={route('reports.switch-unit')}
                                        active={route().current('reports.switch-unit')}
                                    >
                                        Trocar unidade
                                    </ResponsiveNavLink>
                                )}
                                <ResponsiveNavLink
                                    href={route('profile.edit')}
                                    active={route().current('profile.edit')}
                                >
                                    Perfil
                                </ResponsiveNavLink>
                                {dropdownMenuItems.map((item) => (
                                    <div key={item.key}>{item.node}</div>
                                ))}
                                <ResponsiveNavLink
                                    as="button"
                                    method="post"
                                    href={route('logout')}
                                >
                                    Sair
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow dark:bg-gray-800">
                    <div
                        className={`mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-2 ${headerClassName}`}
                    >
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
