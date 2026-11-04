'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {BookPlusIcon, CheckIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {EntityName} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {postAsset} from '@/lib/api/assets';
import {iri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {EventCategory, StoryEvent, track} from '@/lib/analytics/events';

type Props = ModalProps<Asset> & {assets: Asset[]; onComplete?: () => void};

/**
 * Creates a story holding the given assets (of a single workspace), in one
 * request. The story is created where the first asset lives (its workspace
 * root when that asset belongs to a story). The cover is chosen among the
 * assets having a file, the first one by default.
 */
export function CreateStoryDialog({
    open,
    onOpenChange,
    resolve,
    assets,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const [name, setName] = useState('');
    const [coverId, setCoverId] = useState(
        () => assets.find(a => a.source)?.id
    );

    const submit = async () => {
        const [first] = assets;
        const reference = first.referenceCollection;
        const story = await postAsset({
            name: name.trim(),
            isStory: true,
            storyAssets: assets.map(a => a.id),
            storyCover: coverId,
            ...(reference && !reference.storyAsset
                ? {collection: iri(EntityName.Collection, reference.id)}
                : {workspace: iri(EntityName.Workspace, first.workspace.id)}),
        });
        track(EventCategory.Story, StoryEvent.Create, {value: assets.length});
        toast.success(
            t('story.create.done', 'Story {{name}} created', {
                name: story.name,
            })
        );
        onComplete?.();
        resolve?.(story);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('story.create.title', 'Create a story')}
            description={t(
                'story.create.description',
                'The {{count}} selected assets will be added to the new story.',
                {count: assets.length}
            )}
            submitLabel={t('common.create', 'Create')}
            submitIcon={<BookPlusIcon />}
            canSubmit={!!name.trim()}
            dirty={!!name}
            onSubmit={submit}
        >
            <FormRow label={t('story.create.name', 'Story name')}>
                <Input
                    autoFocus
                    value={name}
                    onChange={e => setName(e.target.value)}
                    data-testid="create-story-name"
                />
            </FormRow>
            {coverId ? (
                <FormRow label={t('upload.story_cover', 'Story cover')}>
                    <ul
                        className="grid max-h-64 grid-cols-[repeat(auto-fill,minmax(5rem,1fr))] gap-2 overflow-y-auto p-1"
                        role="radiogroup"
                        data-testid="create-story-cover"
                    >
                        {assets.map(a => {
                            const selected = a.id === coverId;

                            return (
                                <li key={a.id}>
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        disabled={!a.source}
                                        title={a.name ?? undefined}
                                        onClick={() => setCoverId(a.id)}
                                        className={cn(
                                            'relative block aspect-square w-full overflow-hidden rounded-md border bg-media-bg',
                                            selected
                                                ? 'ring-2 ring-primary'
                                                : 'hover:ring-2 hover:ring-primary/50',
                                            'disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:ring-0'
                                        )}
                                    >
                                        <AssetThumb asset={a} size={80} />
                                        {selected ? (
                                            <span className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                <CheckIcon className="size-3.5" />
                                            </span>
                                        ) : null}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </FormRow>
            ) : null}
        </FormDialog>
    );
}
