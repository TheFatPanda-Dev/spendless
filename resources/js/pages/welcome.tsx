import { Head, Link } from '@inertiajs/react';
import ArrowRight from 'lucide-react/dist/esm/icons/arrow-right.js';
import type { ReactNode } from 'react';
import { BudgetRing, HeroFigure, SERIES, StackedBar } from '@/components/kit';
import { formatMoney } from '@/lib/money';
import { login, privacy, register, terms } from '@/routes';

/**
 * Illustrative figures from the identity kit's reference overview. They are shown
 * with an "Example" label and never stand for anyone's real data.
 */
const exampleCategories = [
    { name: 'Home', amount: 680 },
    { name: 'Groceries', amount: 295.4 },
    { name: 'Dining', amount: 148 },
    { name: 'Transport', amount: 84 },
    { name: 'Other', amount: 58 },
];

const exampleBudget = { left: 734.6, spent: 1265.4, amount: 2000, percent: 63 };

const plumbing = [
    {
        term: 'EU banks',
        detail: 'Synced through open banking, four times a day.',
    },
    {
        term: 'UK banks',
        detail: 'Synced through Plaid where it is available, or imported from a statement file (CSV, OFX or XLSX) with a preview first.',
    },
    {
        term: 'Investments and savings',
        detail: 'Interactive Brokers updates daily. A Cash ISA and a pension are tracked by value.',
    },
    {
        term: 'Euros and pounds',
        detail: 'Side by side, each amount in its own currency.',
    },
    {
        term: 'Categories',
        detail: 'Sorted automatically, with Slovenian and UK merchant names. A category you pick by hand is never overwritten.',
    },
];

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-wordmark-mint';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    return (
        <>
            <Head title="Money made simple" />

            <div className="min-h-svh bg-background text-foreground">
                <Poster canRegister={canRegister} />
                <PromiseSection />
                <Plumbing />
                <Closing canRegister={canRegister} />
            </div>
        </>
    );
}

function Poster({ canRegister }: { canRegister: boolean }) {
    return (
        <section className="grid bg-campaign text-campaign-foreground desk:min-h-svh desk:grid-cols-2">
            <div className="flex flex-col px-5 pt-6 pb-12 tablet:px-12 tablet:pt-10 tablet:pb-16 desk:px-16 desk:pb-20">
                <img
                    src="/images/brand/lockup-on-dark.svg"
                    alt="SpendLess"
                    width={179}
                    height={48}
                    className="h-10 w-auto self-start tablet:h-12"
                />

                <div className="mt-14 animate-in duration-700 ease-out-quart fade-in slide-in-from-bottom-3 motion-reduce:animate-none tablet:mt-24 desk:mt-auto">
                    <h1 className="max-w-[11ch] text-[length:clamp(3.25rem,9vw,6rem)] leading-[0.98] font-light tracking-[-0.03em]">
                        Money made simple
                    </h1>

                    <div className="mt-8 border-t border-campaign-rule pt-6 tablet:mt-10">
                        <p className="text-xl leading-snug text-campaign-foreground tablet:text-[1.375rem]">
                            See what went out. Know what’s left.
                        </p>
                        <p className="mt-3 max-w-md text-base leading-relaxed text-pretty text-campaign-muted">
                            Your banks, investments and savings in the EU and
                            the UK, in one place, in euros and pounds.
                        </p>
                    </div>

                    <Actions canRegister={canRegister} className="mt-8" />
                </div>
            </div>

            <div className="relative h-[22rem] overflow-hidden tablet:h-[30rem] desk:h-auto">
                <img
                    src="/images/brand/paper-curls.webp"
                    alt=""
                    width={1254}
                    height={1254}
                    className="absolute inset-0 size-full object-cover object-center"
                />
            </div>
        </section>
    );
}

function Actions({
    canRegister,
    className,
}: {
    canRegister: boolean;
    className?: string;
}) {
    return (
        <div
            className={`flex flex-wrap items-center gap-x-6 gap-y-4 ${className ?? ''}`}
        >
            <Link
                href={login()}
                className={`inline-flex h-12 items-center rounded-lg bg-wordmark-mint px-7 text-base font-medium text-wordmark-mint-foreground transition-[background-color,transform] hover:bg-wordmark-mint-hover active:scale-[0.98] ${focusRing}`}
            >
                Log in
            </Link>

            {canRegister ? (
                <Link
                    href={register()}
                    className={`group inline-flex items-center gap-1.5 rounded-sm text-base font-medium text-campaign-foreground underline-offset-4 hover:underline ${focusRing}`}
                >
                    Create an account
                    <ArrowRight
                        className="size-4 transition-transform group-hover:translate-x-0.5"
                        strokeWidth={1.75}
                        aria-hidden
                    />
                </Link>
            ) : (
                <p className="text-[15px] text-campaign-muted">
                    Sign-ups are closed for now.
                </p>
            )}
        </div>
    );
}

function ExampleLabel({ className }: { className?: string }) {
    return (
        <span
            className={`rounded-full border px-2.5 py-0.5 text-sm leading-5 ${className ?? ''}`}
        >
            Example
        </span>
    );
}

function PromiseHalf({
    title,
    detail,
    children,
}: {
    title: string;
    detail: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col">
            <h2 className="text-screen-title font-normal tracking-[-0.01em] tablet:text-page-title">
                {title}
            </h2>
            <p className="mt-3 max-w-md text-base leading-relaxed text-pretty text-muted-foreground tablet:text-lg">
                {detail}
            </p>
            <div className="mt-8 flex flex-1 flex-col">{children}</div>
        </div>
    );
}

function PromiseSection() {
    const total = exampleCategories.reduce(
        (sum, category) => sum + category.amount,
        0,
    );

    return (
        <section className="mx-auto grid w-full max-w-[75rem] gap-16 px-5 py-20 tablet:px-12 tablet:py-28 desk:grid-cols-2 desk:gap-12 desk:px-16">
            <PromiseHalf
                title="See what went out."
                detail="Every transaction lands in a category, so a month reads as a few lines, ranked by what cost the most."
            >
                <div className="flex flex-1 flex-col rounded-card border border-border bg-card p-5 text-card-foreground tablet:p-7">
                    <div className="mb-5 flex items-center justify-between gap-4">
                        <p className="text-section font-medium">
                            Spending by category
                        </p>
                        <ExampleLabel className="border-border text-muted-foreground" />
                    </div>
                    <StackedBar
                        parts={exampleCategories.map((category, index) => ({
                            key: category.name,
                            amount: category.amount,
                            color: SERIES[index],
                            label: `${category.name} ${Math.round((category.amount / total) * 100)}%`,
                        }))}
                    />
                    <ul className="mt-5">
                        {exampleCategories.map((category, index) => (
                            <li
                                key={category.name}
                                className="flex items-center justify-between gap-4 border-t border-border py-3 text-base first:border-t-0"
                            >
                                <span className="flex items-center gap-3">
                                    <span
                                        className="size-2.5 rounded-full"
                                        style={{
                                            backgroundColor: SERIES[index],
                                        }}
                                        aria-hidden
                                    />
                                    {category.name}
                                </span>
                                <span className="amount">
                                    {formatMoney(category.amount, 'EUR')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </PromiseHalf>

            <PromiseHalf
                title="Know what’s left."
                detail="Set a monthly budget and the first number you see is what you can still spend, with the period and the budget beside it."
            >
                <div className="flex flex-1 flex-col rounded-card bg-hero p-5 text-hero-foreground tablet:p-7">
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-section font-medium">
                            Monthly budget
                        </p>
                        <ExampleLabel className="border-hero-foreground/20 text-hero-muted" />
                    </div>
                    <div className="mt-6 mb-8 flex items-center justify-between gap-4">
                        <div className="@container min-w-0 flex-1">
                            <p className="text-lg text-hero-muted">
                                Left to spend
                            </p>
                            <HeroFigure
                                value={formatMoney(exampleBudget.left, 'EUR')}
                            />
                        </div>
                        <BudgetRing
                            percent={exampleBudget.percent}
                            size={132}
                            className="size-24 tablet:size-[132px]"
                        />
                    </div>
                    <div className="mt-auto grid grid-cols-2 gap-6 border-t border-hero-foreground/15 pt-5">
                        <div>
                            <p className="text-[15px] text-hero-muted">
                                Spent so far
                            </p>
                            <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                                {formatMoney(exampleBudget.spent, 'EUR')}
                            </p>
                        </div>
                        <div>
                            <p className="text-[15px] text-hero-muted">
                                Budget
                            </p>
                            <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                                {formatMoney(exampleBudget.amount, 'EUR')}
                            </p>
                        </div>
                    </div>
                </div>
            </PromiseHalf>
        </section>
    );
}

function Plumbing() {
    return (
        <section className="border-t border-border">
            <div className="mx-auto grid w-full max-w-[75rem] gap-10 px-5 py-20 tablet:px-12 tablet:py-28 desk:grid-cols-[5fr_7fr] desk:gap-12 desk:px-16">
                <div>
                    <h2 className="max-w-[14ch] text-screen-title font-normal tracking-[-0.01em] text-balance tablet:text-page-title">
                        Made for money in two countries
                    </h2>
                    <p className="mt-4 max-w-sm text-base leading-relaxed text-pretty text-muted-foreground">
                        EU banks and investments update in the background; UK
                        statements import from a file, with a preview first.
                    </p>
                </div>

                <dl>
                    {plumbing.map((row) => (
                        <div
                            key={row.term}
                            className="grid gap-1 border-t border-border py-5 tablet:grid-cols-[13rem_1fr] tablet:gap-6"
                        >
                            <dt className="text-base font-medium">
                                {row.term}
                            </dt>
                            <dd className="max-w-xl text-base leading-relaxed text-pretty text-muted-foreground">
                                {row.detail}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

function Closing({ canRegister }: { canRegister: boolean }) {
    return (
        <footer className="bg-campaign text-campaign-foreground">
            <div className="mx-auto w-full max-w-[75rem] px-5 pt-20 pb-10 tablet:px-12 tablet:pt-24 desk:px-16">
                <div className="flex flex-col gap-10 desk:flex-row desk:items-end desk:justify-between">
                    <p className="max-w-[16ch] text-[length:clamp(2.25rem,5vw,3.5rem)] leading-[1.05] font-light tracking-[-0.02em] text-balance">
                        All your money, in one place.
                    </p>
                    <Actions canRegister={canRegister} />
                </div>

                <div className="mt-20 flex flex-col gap-6 border-t border-campaign-rule pt-8 tablet:flex-row tablet:items-center tablet:justify-between">
                    <img
                        src="/images/brand/lockup-on-dark.svg"
                        alt="SpendLess"
                        width={179}
                        height={48}
                        className="h-9 w-auto self-start"
                    />
                    <nav
                        aria-label="Legal"
                        className="flex flex-wrap items-center gap-x-6 gap-y-2 text-[15px] text-campaign-muted"
                    >
                        <Link
                            href={privacy()}
                            className={`rounded-sm underline-offset-4 hover:text-campaign-foreground hover:underline ${focusRing}`}
                        >
                            Privacy
                        </Link>
                        <Link
                            href={terms()}
                            className={`rounded-sm underline-offset-4 hover:text-campaign-foreground hover:underline ${focusRing}`}
                        >
                            Terms
                        </Link>
                        <span>© {new Date().getFullYear()} SpendLess</span>
                    </nav>
                </div>
            </div>
        </footer>
    );
}
