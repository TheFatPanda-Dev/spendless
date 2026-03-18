import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { home } from '@/routes';

export default function Privacy() {
    const { name, support_email: supportEmail } = usePage().props;
    const sectionClass =
        'space-y-2 rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm backdrop-blur-sm';

    return (
        <>
            <Head title="Privacy Policy" />

            <div className="min-h-screen bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.14),transparent_48%),radial-gradient(circle_at_90%_20%,rgba(45,212,191,0.12),transparent_36%)] text-foreground">
                <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-10 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-brand/20 bg-linear-to-br from-brand/15 via-background to-brand/5 p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-2">
                                <p className="inline-flex rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-medium tracking-wide text-brand">
                                    EU Privacy
                                </p>
                                <h1 className="text-2xl font-semibold text-foreground sm:text-3xl">
                                    Privacy Policy
                                </h1>
                                <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                    This page explains how {name} processes and
                                    protects personal data under GDPR.
                                </p>
                            </div>

                            <Link
                                href={home()}
                                className="inline-flex items-center gap-1.5 rounded-full border border-brand/25 bg-background/70 px-3 py-1.5 text-sm font-medium text-brand transition-colors hover:bg-brand/10 hover:text-brand"
                            >
                                <ArrowLeft
                                    className="h-4 w-4"
                                    aria-hidden="true"
                                />
                                Back to home
                            </Link>
                        </div>
                    </div>

                    <div className="space-y-5 text-sm leading-6 text-muted-foreground">
                        <p className="rounded-xl border border-border/60 bg-background/70 px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Last updated: 16 March 2026
                        </p>

                        <p className="rounded-2xl border border-border/60 bg-background/70 p-5">
                            This Privacy Policy explains how {name} ("we", "us",
                            "our") processes personal data when you use the
                            application and related services. It is written with
                            the EU General Data Protection Regulation (GDPR) and
                            related EU privacy rules in mind.
                        </p>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                1. Who is responsible for your data
                            </h2>
                            <p>
                                The data controller is the operator of {name}.
                                If you have privacy questions or requests,
                                contact us at{' '}
                                {supportEmail ? (
                                    <a
                                        href={`mailto:${supportEmail}`}
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        {supportEmail}
                                    </a>
                                ) : (
                                    'the support channel listed in the app'
                                )}
                                .
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                2. Categories of personal data we process
                            </h2>
                            <ul className="list-disc space-y-1 pl-5">
                                <li>
                                    Account and identity data (for example,
                                    name, email address, authentication data).
                                </li>
                                <li>
                                    Security data (for example, login attempts,
                                    password reset and two-factor related data).
                                </li>
                                <li>
                                    Financial profile data you create (wallets,
                                    categories, manual transactions, balances,
                                    preferences).
                                </li>
                                <li>
                                    Connected banking data from third-party
                                    providers (for example account identifiers,
                                    balances, and transaction data), only when
                                    you choose to connect an account.
                                </li>
                                <li>
                                    Technical and usage data (for example,
                                    timestamps, logs, device or browser metadata
                                    needed for security and troubleshooting).
                                </li>
                            </ul>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                3. Purposes and legal bases (GDPR Art. 6)
                            </h2>
                            <ul className="list-disc space-y-1 pl-5">
                                <li>
                                    To provide the service and your account
                                    features (contract performance, Art.
                                    6(1)(b)).
                                </li>
                                <li>
                                    To sync linked bank accounts and display
                                    transactions and balances at your request
                                    (contract performance, Art. 6(1)(b)).
                                </li>
                                <li>
                                    To secure accounts and prevent abuse,
                                    including authentication and rate limiting
                                    (legitimate interests, Art. 6(1)(f)).
                                </li>
                                <li>
                                    To comply with legal obligations where
                                    applicable (Art. 6(1)(c)).
                                </li>
                                <li>
                                    Where required, based on your consent (Art.
                                    6(1)(a)), which you can withdraw at any time
                                    without affecting prior lawful processing.
                                </li>
                            </ul>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                4. Banking integrations and open banking
                            </h2>
                            <p>
                                If you connect financial accounts, we use
                                third-party banking providers (such as Plaid and
                                Enable Banking) to access account and
                                transaction data on your behalf. We only process
                                data needed to provide {name} features.
                            </p>
                            <p>
                                Connection tokens and sensitive integration
                                fields are stored with encryption at rest where
                                supported by the platform. You can disconnect a
                                linked account in app settings.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                5. Data sharing and recipients
                            </h2>
                            <p>We do not sell your personal data.</p>
                            <p>
                                We may share data with service providers acting
                                on our instructions, including:
                            </p>
                            <ul className="list-disc space-y-1 pl-5">
                                <li>Banking connectivity providers.</li>
                                <li>
                                    Authentication, infrastructure, or email
                                    delivery providers.
                                </li>
                                <li>
                                    Professional advisers or authorities where
                                    required by law.
                                </li>
                            </ul>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                6. International transfers
                            </h2>
                            <p>
                                Some providers may process data outside the
                                European Economic Area (EEA). When this happens,
                                we rely on appropriate safeguards, such as
                                Standard Contractual Clauses (SCCs), and
                                additional measures where needed.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                7. Data retention
                            </h2>
                            <p>
                                We keep personal data only as long as necessary
                                for the purposes above, including legal,
                                accounting, and security needs. If you delete
                                your account, we delete or anonymize data unless
                                retention is legally required.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                8. Security
                            </h2>
                            <p>
                                We use technical and organizational safeguards,
                                including access controls, encryption for
                                sensitive fields, monitoring, and
                                least-privilege practices. No method of
                                transmission or storage is fully risk-free, but
                                we work to protect your data appropriately.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                9. Your rights under GDPR
                            </h2>
                            <p>You may have the right to:</p>
                            <ul className="list-disc space-y-1 pl-5">
                                <li>Access your personal data.</li>
                                <li>Rectify inaccurate data.</li>
                                <li>Erase data in certain circumstances.</li>
                                <li>Restrict or object to processing.</li>
                                <li>Data portability.</li>
                                <li>
                                    Withdraw consent where processing is based
                                    on consent.
                                </li>
                                <li>
                                    Lodge a complaint with your local data
                                    protection authority.
                                </li>
                            </ul>
                            <p>
                                To exercise rights, contact us at{' '}
                                {supportEmail ? (
                                    <a
                                        href={`mailto:${supportEmail}`}
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        {supportEmail}
                                    </a>
                                ) : (
                                    'the app support channel'
                                )}
                                . We may ask for identity verification before
                                fulfilling a request.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                10. Children
                            </h2>
                            <p>
                                This service is not intended for children under
                                the age required by applicable law. We do not
                                knowingly process children&apos;s personal data
                                for account use.
                            </p>
                        </section>

                        <section className={sectionClass}>
                            <h2 className="text-base font-semibold text-foreground">
                                11. Changes to this policy
                            </h2>
                            <p>
                                We may update this Privacy Policy from time to
                                time. Material updates will be published on this
                                page with a revised "Last updated" date.
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
