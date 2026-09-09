'use client';

import {useTranslation} from 'react-i18next';
import type {Asset} from '@/types/api';
import {useSelection} from '../SelectionProvider';
import {Checkbox} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {
    ActionContext,
    useAssetActions,
} from '@/features/assets/actions/useAssetActions';
import {BasketSwitcher} from '@/features/baskets/BasketSwitcher';
import {useAuth} from '@/lib/auth/AuthProvider';

/**
 * Master checkbox + bulk actions for the current selection.
 */
export function SelectionActions({
    pages,
    context,
}: {
    pages: Asset[][];
    context?: ActionContext;
}) {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const selection = useSelection();
    const total = pages.flat().length;
    const count = selection.selection.length;
    const groups = useAssetActions(selection.selection, {context});
    const bulkActions = groups.flat().filter(a => a.bulk && a.id !== 'basket');

    return (
        <div className="flex items-center gap-1">
            <Tooltip
                content={
                    count === total && total > 0
                        ? t('list.unselect_all', 'Unselect all')
                        : t('list.select_all', 'Select all (Ctrl+A)')
                }
            >
                <span className="flex items-center px-1">
                    <Checkbox
                        checked={
                            count === 0
                                ? false
                                : count === total
                                  ? true
                                  : 'indeterminate'
                        }
                        disabled={total === 0}
                        onCheckedChange={() =>
                            count === total
                                ? selection.clear()
                                : selection.selectAll(pages)
                        }
                        aria-label={t('list.select_all', 'Select all')}
                    />
                </span>
            </Tooltip>
            {isAuthenticated && (context?.basket ?? true) ? (
                <BasketSwitcher
                    selection={selection.selection}
                    onAdded={selection.clear}
                />
            ) : null}
            {count > 0
                ? bulkActions.map(action => (
                      <Tooltip key={action.id} content={action.label}>
                          <Button
                              variant={action.destructive ? 'ghost' : 'ghost'}
                              size="sm"
                              className={
                                  action.destructive
                                      ? 'text-destructive hover:text-destructive'
                                      : undefined
                              }
                              onClick={() => action.run()}
                              disabled={action.disabled}
                          >
                              {action.icon}
                              <span className="hidden xl:inline">
                                  {action.label}
                              </span>
                          </Button>
                      </Tooltip>
                  ))
                : null}
        </div>
    );
}
