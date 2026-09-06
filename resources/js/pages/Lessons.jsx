import { Head } from '@inertiajs/react';

export default function Lessons({ lessons }) {
    return (
        <>
            <Head title="Lessons" />

            <main className="min-h-screen bg-slate-950 px-6 py-16 text-slate-100 sm:px-8">
                <div className="mx-auto max-w-4xl space-y-8">
                    <header className="space-y-3">
                        <div className="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">
                            TingXie HERO
                        </div>
                        <h1 className="text-4xl font-semibold tracking-tight text-white">
                            Lessons
                        </h1>
                        <p className="text-slate-300">
                            Read-only lessons loaded through Laravel and Supabase PostgreSQL.
                        </p>
                    </header>

                    <div className="grid gap-5">
                        {lessons.map((lesson) => (
                            <article
                                className="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-cyan-950/20"
                                key={lesson.id}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <h2 className="text-2xl font-semibold text-white">
                                        {lesson.title}
                                    </h2>
                                    <span className="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-sm font-medium text-cyan-200">
                                        {lesson.moe_level}
                                    </span>
                                </div>

                                <ul className="mt-5 flex flex-wrap gap-2">
                                    {lesson.word_list.map((word) => (
                                        <li
                                            className="rounded-lg bg-slate-800 px-3 py-2 text-sm text-slate-200"
                                            key={word}
                                        >
                                            {word}
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                </div>
            </main>
        </>
    );
}
