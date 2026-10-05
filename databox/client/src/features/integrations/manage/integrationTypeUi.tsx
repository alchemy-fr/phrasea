'use client';

import type {TFunction} from 'i18next';
import {
    BlendIcon,
    CaptionsIcon,
    EraserIcon,
    FileSearchIcon,
    FlaskConicalIcon,
    GlobeIcon,
    ImagesIcon,
    LanguagesIcon,
    LucideIcon,
    MicIcon,
    PaintbrushIcon,
    PuzzleIcon,
    ScanEyeIcon,
    ServerCogIcon,
    ShieldCheckIcon,
    StampIcon,
    UploadIcon,
    UserCheckIcon,
    WebhookIcon,
} from 'lucide-react';
import type {IntegrationCategory, IntegrationType} from '@/types/api';
import {cn} from '@/lib/utils/cn';

const icons: Record<string, LucideIcon> = {
    'core.rendition': ImagesIcon,
    'core.read_metadata': FileSearchIcon,
    'core.file_analyzer': ShieldCheckIcon,
    'core.watermark': StampIcon,
    'core.webhook': WebhookIcon,
    'core.moderation': UserCheckIcon,
    'blurhash': BlendIcon,
    'aws.rekognition': ScanEyeIcon,
    'aws.transcribe': CaptionsIcon,
    'aws.translate': LanguagesIcon,
    'happyscribe': MicIcon,
    'remove.bg': EraserIcon,
    'tui.photo-editor': PaintbrushIcon,
    'phrasea.expose': GlobeIcon,
    'phrasea.uploader': UploadIcon,
    'phraseanet.renditions': ServerCogIcon,
    'test.asset_operation': FlaskConicalIcon,
};

export const integrationCategories: IntegrationCategory[] = [
    'processing',
    'ai',
    'editor',
    'publication',
    'ingest',
    'automation',
    'other',
];

const categoryTones: Record<IntegrationCategory, string> = {
    processing: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    ai: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    editor: 'bg-pink-500/10 text-pink-700 dark:text-pink-300',
    publication: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    ingest: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    automation: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
    other: 'bg-muted text-muted-foreground',
};

export function categoryLabel(t: TFunction, category: IntegrationCategory) {
    switch (category) {
        case 'processing':
            return t('integration.category.processing', 'File processing');
        case 'ai':
            return t('integration.category.ai', 'AI & recognition');
        case 'editor':
            return t('integration.category.editor', 'Editors');
        case 'publication':
            return t('integration.category.publication', 'Publication');
        case 'ingest':
            return t('integration.category.ingest', 'Ingestion');
        case 'automation':
            return t('integration.category.automation', 'Automation');
        default:
            return t('integration.category.other', 'Other');
    }
}

export function featureLabel(t: TFunction, feature: string) {
    switch (feature) {
        case 'workflow':
            return t('integration.feature.workflow', 'Workflow');
        case 'asset-view':
            return t('integration.feature.asset_view', 'Asset view');
        case 'basket':
            return t('integration.feature.basket', 'Baskets');
        default:
            return feature;
    }
}

export function mainCategory(
    type: Pick<IntegrationType, 'categories'>
): IntegrationCategory {
    return type.categories[0] ?? 'other';
}

export function IntegrationTypeIcon({
    type,
    className,
}: {
    type: Pick<IntegrationType, 'name' | 'categories'>;
    className?: string;
}) {
    const Icon = icons[type.name] ?? PuzzleIcon;

    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-lg [&>svg]:size-5',
                categoryTones[mainCategory(type)] ?? categoryTones.other,
                className
            )}
        >
            <Icon />
        </span>
    );
}
