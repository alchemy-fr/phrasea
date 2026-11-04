'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CheckIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {FormDialog} from '@/components/modals/FormDialog';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {Thumbnail} from '@/features/publication/Thumbnail';
import {cn} from '@/lib/utils/cn';

/**
 * Picks the cover of a publication among its assets — whatever their type,
 * the icon of the type standing for those without a thumbnail — or none
 * (the first asset is then used).
 */
export function CoverDialog({
    open,
    onOpenChange,
    resolve,
    assets,
    selected: initial,
}: ModalProps<{cover: string | null}> & {
    assets: Asset[];
    selected: string | null;
}) {
    const {t} = useTranslation();
    const [selected, setSelected] = useState(initial);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('cover.title', 'Cover image')}
            description={t(
                'cover.description',
                'Without a cover, the first asset of the publication is used.'
            )}
            submitLabel={t('cover.submit', 'Set Cover')}
            footerStart={
                <button
                    type="button"
                    className="text-sm text-muted-foreground underline-offset-4 hover:underline disabled:opacity-50"
                    disabled={!selected}
                    data-testid="cover-none"
                    onClick={() => setSelected(null)}
                >
                    {t('cover.none', 'No cover')}
                </button>
            }
            onSubmit={() => resolve?.({cover: selected})}
        >
            {assets.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('publication.no_asset', 'No asset available')}
                </p>
            ) : (
                <ul
                    data-testid="cover-assets"
                    className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5"
                >
                    {assets.map(asset => {
                        const isSelected = asset.id === selected;

                        return (
                            <li key={asset.id}>
                                <button
                                    type="button"
                                    aria-pressed={isSelected}
                                    title={asset.title || asset.originalName}
                                    onClick={() =>
                                        setSelected(
                                            isSelected ? null : asset.id
                                        )
                                    }
                                    className={cn(
                                        'relative block aspect-square w-full cursor-pointer overflow-hidden rounded-md outline-none ring-offset-2 ring-offset-background focus-visible:ring-2 focus-visible:ring-ring',
                                        isSelected && 'ring-2 ring-primary'
                                    )}
                                >
                                    <Thumbnail
                                        asset={asset}
                                        className="size-full"
                                    />
                                    {isSelected ? (
                                        <span className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                            <CheckIcon className="size-3.5" />
                                        </span>
                                    ) : null}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </FormDialog>
    );
}
