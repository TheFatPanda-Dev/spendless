import { Link, router } from '@inertiajs/react';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TagChip } from '@/components/tag-chip';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    tags: TagOption[];
    selectedIds: number[];
    onToggle: (tagId: number) => void;
    onCreated: (tag: TagOption) => void;
    colors: TagColorOption[];
    colorMap: Map<string, TagColorOption>;
    disabled?: boolean;
};

/**
 * Tag a transaction: toggle existing tags, or type a new one ("Golf", "Flat Ljubljana").
 */
export function TransactionTagPicker({
    tags,
    selectedIds,
    onToggle,
    onCreated,
    colors,
    colorMap,
    disabled = false,
}: Props) {
    const [newName, setNewName] = useState('');
    const [isCreating, setIsCreating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const createTag = (event: FormEvent): void => {
        event.preventDefault();
        const name = newName.trim();

        if (name === '' || isCreating) {
            return;
        }

        const existing = tags.find(
            (tag) => tag.name.toLowerCase() === name.toLowerCase(),
        );

        if (existing) {
            if (!selectedIds.includes(existing.id)) {
                onToggle(existing.id);
            }

            setNewName('');

            return;
        }

        const used = new Set(tags.map((tag) => tag.color));
        const color =
            colors.find((option) => !used.has(option.value))?.value ??
            colors[tags.length % Math.max(colors.length, 1)]?.value ??
            'slate';

        router.post(
            '/tags',
            { name, color },
            {
                preserveScroll: true,
                preserveState: true,
                preserveUrl: true,
                only: ['tags'],
                onStart: () => {
                    setIsCreating(true);
                    setError(null);
                },
                onFinish: () => setIsCreating(false),
                onError: (errors) =>
                    setError(errors.name ?? 'Could not create the tag.'),
                onSuccess: (page) => {
                    const created = (
                        (page.props.tags ?? []) as TagOption[]
                    ).find(
                        (tag) => tag.name.toLowerCase() === name.toLowerCase(),
                    );

                    if (created) {
                        onCreated(created);
                    }

                    setNewName('');
                },
            },
        );
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                    Tags
                </p>
                <Link
                    href="/tags"
                    className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Manage tags
                </Link>
            </div>

            {tags.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                    {tags.map((tag) => (
                        <TagChip
                            key={tag.id}
                            tag={tag}
                            colorMap={colorMap}
                            selected={selectedIds.includes(tag.id)}
                            onClick={() => onToggle(tag.id)}
                            disabled={disabled}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Tags say which car, flat or project a payment was for, e.g.
                    "Golf" or "Flat Ljubljana".
                </p>
            )}

            <form onSubmit={createTag} className="flex gap-2">
                <Input
                    value={newName}
                    onChange={(event) => setNewName(event.target.value)}
                    placeholder="New tag"
                    maxLength={50}
                    aria-label="New tag name"
                    disabled={disabled || isCreating}
                    className="h-9 max-w-56 rounded-xl text-sm"
                />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={disabled || isCreating || newName.trim() === ''}
                    className="h-9 rounded-xl"
                >
                    <Plus className="size-4" />
                    Add
                </Button>
            </form>
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
