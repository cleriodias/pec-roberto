import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div
            className="relative min-h-screen overflow-hidden bg-[var(--surface)] text-[var(--ink)]"
            style={{
                fontFamily: "'Space Grotesk', sans-serif",
                '--surface': '#fff7f2',
                '--ink': '#0f172a',
                '--accent': '#e11d48',
                '--accent-strong': '#be123c',
            }}
        >
            <div className="pointer-events-none absolute -top-24 right-10 h-72 w-72 rounded-full bg-amber-300/40 blur-[120px]" />
            <div className="pointer-events-none absolute -bottom-20 left-0 h-96 w-96 rounded-full bg-rose-400/30 blur-[140px]" />
            <div className="pointer-events-none absolute inset-0 opacity-80 [background-image:radial-gradient(circle_at_top,#ffffff_0%,rgba(255,255,255,0)_60%)]" />

            <div className="relative flex min-h-screen flex-col items-center justify-center px-6 py-10">
                <div className="w-full overflow-hidden rounded-3xl border border-white/70 bg-white/85 px-6 py-6 shadow-xl backdrop-blur sm:max-w-2xl">
                    <div className="mb-5 flex justify-center">
                        <Link href="/">
                            <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                        </Link>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
