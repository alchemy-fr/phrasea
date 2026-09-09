'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CodeIcon, WandSparklesIcon} from 'lucide-react';
import type {AQLQuery} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Textarea} from '@/components/ui/input';
import {Alert, Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import type {SearchContextValue} from '../SearchProvider';
import {parseAQL} from '../aql/parser';
import {astToString} from '../aql/serializer';
import {validateAST} from '../aql/validation';
import {AQLExpression, AQLLogical, AQLQueryAST} from '../aql/types';
import {
    useDefinitionsBySlug,
    useDefinitionsStore,
} from '@/features/attributes/definitionsStore';
import {
    ExpressionBuilder,
    emptyCondition,
    normalizeExpression,
} from './ConditionBuilder';
import {InlineLoader} from '@/components/ui/loader';
import {cn} from '@/lib/utils/cn';

type Props = ModalProps & {
    condition?: AQLQuery;
    /** Called instead of updating the search (e.g. filter rules) */
    onSubmit?: (query: string) => void;
    title?: string;
    /**
     * Search context of the caller. Imperative modals are rendered above
     * SearchProvider, so it must be passed explicitly.
     */
    search?: Pick<SearchContextValue, 'workspaces' | 'upsertCondition'>;
    /** Workspace whose attribute definitions feed the builder */
    workspaceId?: string;
};

type Mode = 'builder' | 'text';

export function ConditionDialog({
    open,
    onOpenChange,
    condition,
    onSubmit,
    title,
    search,
    workspaceId: forcedWorkspaceId,
}: Props) {
    const {t} = useTranslation();
    const workspaceId = forcedWorkspaceId ?? search?.workspaces[0];
    const definitions = useDefinitionsBySlug({workspaceId});
    const loadDefinitions = useDefinitionsStore(s => s.load);
    const loadWorkspace = useDefinitionsStore(s => s.loadWorkspace);
    const loaded = useDefinitionsStore(s => s.loaded);
    const loadingDefinitions = useDefinitionsStore(s => s.loading);
    const [mode, setMode] = useState<Mode>('builder');
    const [text, setText] = useState(condition?.query ?? '');
    const [expression, setExpression] = useState<AQLExpression>(() => {
        const ast = condition ? parseAQL(condition.query) : undefined;

        return ast
            ? normalizeExpression(ast.expression)
            : {operator: AQLLogical.AND, conditions: [emptyCondition()]};
    });
    const [error, setError] = useState<string>();

    useEffect(() => {
        void loadDefinitions();
        if (workspaceId) {
            void loadWorkspace(workspaceId);
        }
    }, [loadDefinitions, loadWorkspace, workspaceId]);

    const builderQuery = useMemo(() => astToString({expression}), [expression]);

    const switchMode = (next: Mode) => {
        setError(undefined);
        if (next === 'text') {
            setText(builderQuery);
        } else {
            const ast = parseAQL(text, false);
            if (!ast && text.trim()) {
                setError(
                    t('search.condition.invalid_syntax', 'Invalid AQL syntax')
                );

                return;
            }
            if (ast) {
                setExpression(normalizeExpression(ast.expression));
            }
        }
        setMode(next);
    };

    const submit = () => {
        const query = mode === 'text' ? text.trim() : builderQuery;
        let ast: AQLQueryAST | undefined;
        try {
            ast = parseAQL(query, true);
            validateAST(ast!, definitions);
        } catch (e: any) {
            setError(e.message);

            return;
        }
        const normalized = astToString(ast);
        if (onSubmit) {
            onSubmit(normalized);
        } else {
            search?.upsertCondition({
                id: condition?.id ?? '',
                query: normalized,
                disabled: condition?.disabled,
                inversed: condition?.inversed,
                renewId: !condition,
            });
        }
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg" className="sm:max-w-4xl">
                <DialogHeader className="flex-row items-center justify-between pr-8">
                    <DialogTitle>
                        {title ??
                            (condition
                                ? t(
                                      'search.condition.edit_title',
                                      'Edit condition'
                                  )
                                : t(
                                      'search.condition.add_title',
                                      'Add condition'
                                  ))}
                    </DialogTitle>
                    <Tabs
                        value={mode}
                        onValueChange={v => switchMode(v as Mode)}
                    >
                        <TabsList>
                            <TabsTrigger value="builder">
                                <WandSparklesIcon />{' '}
                                {t('search.condition.builder', 'Builder')}
                            </TabsTrigger>
                            <TabsTrigger value="text">
                                <CodeIcon /> AQL
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </DialogHeader>
                <DialogBody className="min-h-48 py-2">
                    {!loaded && loadingDefinitions ? (
                        <InlineLoader />
                    ) : mode === 'builder' ? (
                        <ExpressionBuilder
                            expression={expression}
                            onChange={setExpression}
                            definitions={definitions}
                            root
                        />
                    ) : (
                        <Textarea
                            autoFocus
                            spellCheck={false}
                            value={text}
                            onChange={e => {
                                setText(e.target.value);
                                setError(undefined);
                            }}
                            className={cn(
                                'min-h-40 font-mono text-sm',
                                error && 'border-destructive'
                            )}
                            placeholder={
                                'title CONTAINS "report" AND @createdAt > "2024-01-01"'
                            }
                        />
                    )}
                    {mode === 'builder' && builderQuery ? (
                        <pre className="mt-3 overflow-x-auto rounded-md bg-muted px-3 py-2 font-mono text-xs text-muted-foreground">
                            {builderQuery}
                        </pre>
                    ) : null}
                    {error ? (
                        <Alert variant="destructive" className="mt-3">
                            {error}
                        </Alert>
                    ) : null}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button onClick={submit}>
                        {condition
                            ? t('common.save', 'Save')
                            : t('common.add', 'Add')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
