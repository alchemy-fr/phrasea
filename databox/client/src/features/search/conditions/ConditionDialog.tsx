'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CodeIcon, WandSparklesIcon} from 'lucide-react';
import type {AQLQuery} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Textarea} from '@/components/ui/input';
import {Alert, Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {LabeledControl, Switch} from '@/components/ui/controls';
import type {SearchContextValue} from '../SearchProvider';
import {parseAQL} from '../aql/parser';
import {astToString} from '../aql/serializer';
import {fieldsOf, validateAST} from '../aql/validation';
import {AQLExpression, AQLLogical, AQLQueryAST} from '../aql/types';
import {
    indexBySlug,
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
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

type Props = ModalProps<string> & {
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
    resolve,
    condition,
    onSubmit,
    title,
    search,
    workspaceId: forcedWorkspaceId,
}: Props) {
    const {t} = useTranslation();
    const workspaceId = forcedWorkspaceId ?? search?.workspaces[0];
    const [mode, setMode] = useState<Mode>('builder');
    const [text, setText] = useState(condition?.query ?? '');
    const [expression, setExpression] = useState<AQLExpression>(() => {
        const ast = condition ? parseAQL(condition.query) : undefined;

        return ast
            ? normalizeExpression(ast.expression)
            : {operator: AQLLogical.AND, conditions: [emptyCondition()]};
    });
    const [inversed, setInversed] = useState(!!condition?.inversed);
    const [error, setError] = useState<string>();

    // The definitions of the fields used: the others are picked from lists
    const fields = useMemo(() => fieldsOf(expression), [expression]);
    const definitions = useDefinitionsBySlug({workspaceId, slugs: fields});
    const ensure = useDefinitionsStore(s => s.ensure);
    const loadBuiltIn = useDefinitionsStore(s => s.loadBuiltIn);
    const [initialFields] = useState(fields);
    // Once the fields of the condition edited are known (or failed to load)
    const [ready, setReady] = useState(false);
    useEffect(() => {
        let cancelled = false;
        void Promise.all([ensure({slugs: initialFields}), loadBuiltIn()]).then(
            () => {
                if (!cancelled) {
                    setReady(true);
                }
            }
        );

        return () => {
            cancelled = true;
        };
    }, [ensure, loadBuiltIn, initialFields]);

    const builderQuery = useMemo(() => astToString({expression}), [expression]);

    // Compared on the resulting query, so that switching modes is not a change
    const {dirty} = useDirtyState({
        query: mode === 'text' ? text.trim() : builderQuery,
        inversed,
    });

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

    const submit = async () => {
        const query = mode === 'text' ? text.trim() : builderQuery;
        let ast: AQLQueryAST | undefined;
        try {
            ast = parseAQL(query, true);
            // A field typed in AQL may not be known yet
            await ensure({slugs: fieldsOf(ast)});
            validateAST(
                ast!,
                indexBySlug(useDefinitionsStore.getState(), {workspaceId})
            );
        } catch (e: any) {
            setError(e.message);

            // Reported in place, right under the expression
            return false;
        }
        const normalized = astToString(ast);
        if (onSubmit) {
            onSubmit(normalized);
        } else {
            search?.upsertCondition({
                id: condition?.id ?? '',
                query: normalized,
                disabled: condition?.disabled,
                inversed: inversed || undefined,
                renewId: !condition,
            });
        }
        resolve?.(normalized);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            className="sm:max-w-4xl"
            title={
                title ??
                (condition
                    ? t('search.condition.edit_title', 'Edit condition')
                    : t('search.condition.add_title', 'Add condition'))
            }
            headerEnd={
                <Tabs value={mode} onValueChange={v => switchMode(v as Mode)}>
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
            }
            submitLabel={
                condition ? t('common.save', 'Save') : t('common.add', 'Add')
            }
            bodyClassName="min-h-48 py-2"
            footerStart={
                // Filter rules only take the query
                onSubmit ? undefined : (
                    <LabeledControl
                        label={t('search.condition.invert', 'Invert (NOT)')}
                    >
                        <Switch
                            data-testid="condition-inversed"
                            checked={inversed}
                            onCheckedChange={setInversed}
                        />
                    </LabeledControl>
                )
            }
            dirty={dirty}
            onSubmit={submit}
        >
            {!ready ? (
                <InlineLoader />
            ) : mode === 'builder' ? (
                <ExpressionBuilder
                    expression={expression}
                    onChange={setExpression}
                    definitions={definitions}
                    workspaceId={workspaceId}
                    root
                />
            ) : (
                <Textarea
                    autoFocus
                    data-testid="condition-aql"
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
                <div className="mt-3 space-y-1">
                    <div className="text-xs font-medium text-muted-foreground">
                        {t('search.condition.aql_preview', 'Preview AQL')}
                    </div>
                    <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2 font-mono text-xs text-muted-foreground">
                        {builderQuery}
                    </pre>
                </div>
            ) : null}
            {error ? (
                <Alert variant="destructive" className="mt-3">
                    {error}
                </Alert>
            ) : null}
        </FormDialog>
    );
}
