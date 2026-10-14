'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {BookPlusIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {EntityName} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {postAsset} from '@/lib/api/assets';
import {iri} from '@/lib/utils/iri';

type Props = ModalProps<Asset> & {assets: Asset[]; onComplete?: () => void};

/**
 * Creates a story holding the given assets (of a single workspace), in one
 * request. The story is created where the first asset lives (its workspace
 * root when that asset belongs to a story); the first asset having a file
 * becomes the story cover.
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

    const submit = async () => {
        const [first] = assets;
        const reference = first.referenceCollection;
        const story = await postAsset({
            name: name.trim(),
            isStory: true,
            storyAssets: assets.map(a => a.id),
            storyCover: assets.find(a => a.source)?.id,
            ...(reference && !reference.storyAsset
                ? {collection: iri(EntityName.Collection, reference.id)}
                : {workspace: iri(EntityName.Workspace, first.workspace.id)}),
        });
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
        </FormDialog>
    );
}
