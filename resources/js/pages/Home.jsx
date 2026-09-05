import { Head } from '@inertiajs/react';

export default function Home() {
    return (
        <>
            <Head title="Home" />

            <main className="relative min-h-screen overflow-hidden bg-slate-950">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.28),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(34,197,94,0.16),_transparent_32%),linear-gradient(180deg,_rgba(15,23,42,0.96),_rgba(2,6,23,1))]" />

                <div className="relative mx-auto flex min-h-screen w-full max-w-5xl items-center px-6 py-16 sm:px-8 lg:px-10">
                    <section className="w-full space-y-8">
                        <div className="inline-flex items-center rounded-full border border-cyan-400/20 bg-white/5 px-4 py-2 text-sm font-medium text-cyan-200 shadow-lg shadow-cyan-950/20 backdrop-blur">
                            TingXie HERO
                        </div>

                        <div className="max-w-3xl space-y-4">
                            <h1 className="text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                                Laravel + Inertia + React
                            </h1>
                            <p className="max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                                This page is the baseline proof that the Laravel route,
                                Inertia response, React bootstrap, and Tailwind styling are
                                connected end to end.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-cyan-950/20 backdrop-blur">
                                <div className="text-sm font-medium text-cyan-200">Status</div>
                                <div className="mt-2 text-2xl font-semibold text-white">Connected</div>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-cyan-950/20 backdrop-blur">
                                <div className="text-sm font-medium text-cyan-200">Stack</div>
                                <div className="mt-2 text-2xl font-semibold text-white">Inertia v3</div>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-cyan-950/20 backdrop-blur">
                                <div className="text-sm font-medium text-cyan-200">UI</div>
                                <div className="mt-2 text-2xl font-semibold text-white">Tailwind v4</div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
