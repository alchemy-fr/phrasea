'use client';

import {useTranslation} from 'react-i18next';
import {useSelection} from '../SelectionProvider';
import {useResults} from '@/features/search/ResultProvider';
import {SelectionActions} from './SelectionActions';
import {DisplayOptionsMenu} from './DisplayOptionsMenu';
import {formatNumber} from '@/lib/utils/format';
import {useModals} from '@/components/modals/ModalProvider';
import {DebugEsDialog} from '@/features/search/DebugEsDialog';
import {cn} from '@/lib/utils/cn';

export function AssetToolbar() {
    const {t, i18n} = useTranslation();
    const results = useResults();
    const selection = useSelection();
    const {openModal} = useModals();

    return (
        <div className="flex min-h-10 items-center gap-2 border-t px-3 py-1">
            <SelectionActions pages={results.pages} />
            <div className="flex-1" />
            <button
                type="button"
                className={cn(
                    'text-xs text-muted-foreground tabular-nums',
                    results.debug && 'cursor-help hover:underline'
                )}
                onClick={() =>
                    results.debug &&
                    openModal(DebugEsDialog, {debug: results.debug})
                }
                disabled={!results.debug}
            >
                {results.total !== undefined
                    ? selection.selection.length > 0
                        ? t(
                              'list.selected_of_total',
                              '{{selected}} / {{total}} selected',
                              {
                                  selected: formatNumber(
                                      selection.selection.length,
                                      i18n.language
                                  ),
                                  total: formatNumber(
                                      results.total,
                                      i18n.language
                                  ),
                              }
                          )
                        : t('list.total_results', '{{count}} results', {
                              count: results.total,
                          })
                    : ''}
            </button>
            <DisplayOptionsMenu />
        </div>
    );
}
