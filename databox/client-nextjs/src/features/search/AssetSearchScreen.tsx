'use client';

import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {PlusIcon} from 'lucide-react';
import {SearchProvider, useSearch} from './SearchProvider';
import {ResultProvider, useResults} from './ResultProvider';
import {SearchBar} from './SearchBar';
import {SearchConditions} from './conditions/SearchConditions';
import {AssetList} from '@/features/assets/list/AssetList';
import {SelectionProvider} from '@/features/assets/list/SelectionProvider';
import {AssetToolbar} from '@/features/assets/list/toolbar/AssetToolbar';
import {NoResults} from './NoResults';
import {SearchError} from './SearchError';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {useAuth} from '@/lib/auth/AuthProvider';
import {AssetDropzone} from '@/features/upload/AssetDropzone';
import {useModals} from '@/components/modals/ModalProvider';
import {UploadDialog} from '@/features/upload/UploadDialog';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {ExportWatcher} from '@/features/assets/ExportWatcher';
import {useDisplayPreferences} from '@/features/preferences/store';

export function AssetSearchScreen() {
    return (
        <SearchProvider>
            <ResultProvider>
                <SelectionProvider>
                    <SearchScreenContent />
                </SelectionProvider>
            </ResultProvider>
        </SearchProvider>
    );
}

function SearchScreenContent() {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const results = useResults();
    const search = useSearch();
    const {openModal} = useModals();
    const loadDefinitions = useDefinitionsStore(s => s.load);
    const openAsset = useAssetOpener();
    const display = useDisplayPreferences();

    useEffect(() => {
        void loadDefinitions();
    }, [loadDefinitions]);

    const openUpload = (files?: File[]) => {
        openModal(UploadDialog, {
            files,
            workspaceId: search.workspaces[0],
            collectionId: search.collections[0],
        });
    };

    const isEmpty =
        !results.loading &&
        !results.error &&
        results.pages.every(p => p.length === 0);

    return (
        <AssetDropzone
            onDrop={isAuthenticated ? openUpload : undefined}
            className="flex h-full flex-col"
        >
            <div className="sticky top-0 z-20 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <div className="flex flex-col gap-2 px-3 py-2">
                    <SearchBar />
                    <SearchConditions />
                </div>
                <AssetToolbar />
            </div>

            <div className="relative min-h-0 flex-1">
                {results.error ? (
                    <SearchError
                        error={results.error}
                        onRetry={() => results.reload()}
                    />
                ) : isEmpty ? (
                    <NoResults />
                ) : (
                    <AssetList
                        pages={results.pages}
                        loading={results.loading}
                        loadingMore={results.loadingMore}
                        hasMore={results.hasMore}
                        onLoadMore={results.loadMore}
                        searchGeneration={results.searchGeneration}
                        layout={display.layout}
                        thumbSize={display.thumbSize}
                        onOpen={openAsset}
                        searchQuery={search.query}
                    />
                )}
            </div>

            {isAuthenticated ? (
                <Tooltip
                    content={t('upload.add_assets', 'Add assets')}
                    side="left"
                >
                    <Button
                        size="icon"
                        className="absolute right-5 bottom-5 z-20 size-12 rounded-full shadow-lg"
                        onClick={() => openUpload()}
                        aria-label={t('upload.add_assets', 'Add assets')}
                    >
                        <PlusIcon className="size-6" />
                    </Button>
                </Tooltip>
            ) : null}
            <ExportWatcher />
        </AssetDropzone>
    );
}
