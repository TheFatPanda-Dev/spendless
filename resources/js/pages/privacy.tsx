import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy" />

            <div className="min-h-screen bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">
                            Privacy Policy
                        </h1>
                        <Link
                            href={home()}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Back to home
                        </Link>
                    </div>

                    <div className="space-y-4 text-sm text-muted-foreground">
                        <p>
                            This page describes how <span>Spend</span>
                            <span className="text-brand">Less</span> collects,
                            uses, and stores personal and financial data.
                        </p>
                        <p>
                            Connected bank data is used only to provide account
                            synchronization and transaction insights inside the
                            app.
                        </p>
                        <p>
                            If you need full legal wording, replace this
                            placeholder text with your finalized privacy policy.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
