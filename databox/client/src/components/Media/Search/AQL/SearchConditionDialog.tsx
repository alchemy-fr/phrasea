import {AQLQuery, astToString} from "./query.ts";
import {Alert, Button, CircularProgress, FormControlLabel, Switch} from "@mui/material";
import {useTranslation} from 'react-i18next';
import CheckIcon from "@mui/icons-material/Check";
import {StackedModalProps, useModals} from "@alchemy/navigation";
import React, {useMemo} from "react";
import AqlField from "./AQLField.tsx";
import {AppDialog} from '@alchemy/phrasea-ui';
import {parseAQLQuery} from "./AQL.ts";
import nl2br from "react-nl2br";
import ConditionsBuilder from "./Builder/ConditionsBuilder.tsx";
import {useAttributeDefinitionStore} from "../../../../store/attributeDeifnitionStore.ts";
import {AttributeDefinition, StateSetterHandler} from "../../../../types.ts";
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {validateQueryAST} from "./validation.ts";
import {QBExpression} from "./Builder/builderTypes.ts";
import {emptyCondition} from "./Builder/builder.ts";
import {AQLExpression, AQLQueryAST} from "./aqlTypes.ts";
import {BuiltInFilter} from "../search.ts";

type Props = {
    condition: AQLQuery;
    onUpsert: (condition: AQLQuery) => void;
} & StackedModalProps;

export default function SearchConditionDialog({
    condition,
    open,
    modalIndex,
    onUpsert,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [query, __setQuery] = React.useState(condition.query);
    const [textQueryMode, setTextQueryMode] = React.useState(false);

    const [expression, __setExpression] = React.useState<QBExpression>({...emptyCondition});

    const setQuery = (q: string) => {
        if (error) {
            validateQuery(q);
        }
        __setQuery(q);
    }

    const setExpression: StateSetterHandler<QBExpression> = (handler) => {
        __setExpression(p => {
            const newExpression = handler(p);

            if (error) {
                validateAST({expression: newExpression as AQLExpression});
            }

            return newExpression;
        });
    }

    React.useEffect(() => {
        try {
            if (textQueryMode) {
                __setQuery(astToString({
                    expression
                }));
            } else {
                const result = parseAQLQuery(query, true);
                __setExpression((result?.expression || {...emptyCondition}) as QBExpression);
            }
        } catch (e) {
            console.log('error', e);
        }
    }, [textQueryMode]);

    const [error, setError] = React.useState<string | undefined>();

    const isNew = !condition.query;

    const {load, definitions, loaded} = useAttributeDefinitionStore();

    useEffectOnce(() => {
        load();
    }, [load]);

    const definitionsIndex: Record<string, AttributeDefinition> = useMemo(() => {
        const index: Record<string, AttributeDefinition> = {};

        for (const def of definitions) {
            index[def.slug] = def;
        }

        const builtInFilters = [
            {
                slug: BuiltInFilter.Collection,
                fieldType: 'text',
                name: t('built_in_attr.collection', 'Collection'),
            },
            {
                slug: BuiltInFilter.Workspace,
                fieldType: 'text',
                name: t('built_in_attr.workspace', 'Workspace'),
            },
            {
                slug: BuiltInFilter.Tag,
                fieldType: 'text',
                name: t('built_in_attr.tag', 'Tag'),
            },
            {
                slug: BuiltInFilter.CreatedAt,
                fieldType: 'text',
                name: t('built_in_attr.createdAt', 'Created At'),
            },
        ];

        for (let bf of builtInFilters) {
            index[bf.slug] = {
                ...bf,
                id: bf.slug,
                enabled: true,
                builtIn: true,
            } as AttributeDefinition;
        }

        return index;
    }, [definitions]);


    const wrapValidate = (handler: () => void) => {
        try {
            handler();
            setError(undefined);

            return true;
        } catch (e: any) {
            console.trace(e);
            const error = e.message;
            setError(t('search_condition.dialog.error.invalid_query', {
                defaultValue: 'Invalid query: {{error}}',
                error,
            }));
        }

        return false;
    }

    const validateAST = (ast: AQLQueryAST) => wrapValidate(() => {
        validateQueryAST(ast, definitionsIndex);
    });

    const validateQuery = (q: string) => wrapValidate(() => {
        if (!q) {
            setError(t('search_condition.dialog.error.empty_query', 'Empty query'));
            return false;
        }

        const result = parseAQLQuery(q, true)!;
        validateQueryAST(result, definitionsIndex);
    })

    return <AppDialog
        maxWidth={'lg'}
        onClose={closeModal}
        title={isNew ? t('search_condition.dialog.edit_condition', 'Edit Condition') : t('search_condition.dialog.add_condition', 'Add Condition')}
        open={open}
        modalIndex={modalIndex}
        actions={({onClose}) => (
            <>
                <div style={{
                    flexGrow: 1,
                }}>
                    <FormControlLabel
                        control={<Switch
                            checked={textQueryMode}
                            onChange={(_e, checked) => setTextQueryMode(checked)}

                        />} label={textQueryMode ? t('search_condition.dialog.switch_to_builder', 'Switch to Builder') : t('search_condition.dialog.switch_to_text', 'Switch to Text')} />
                </div>
                <div>
                <Button onClick={onClose}>
                    {t('dialog.cancel', 'Cancel')}
                </Button>
                <Button
                    startIcon={<CheckIcon/>}
                    onClick={() => {
                        const finalQuery: string = textQueryMode ? query : astToString({
                            expression
                        });
                        if (validateQuery(finalQuery)) {
                            closeModal();
                            onUpsert({
                                ...condition,
                                query: finalQuery,
                            });
                        }
                    }}
                    color={'primary'}
                    variant={'contained'}
                >
                    {isNew ? t('search_condition.dialog.submit_add', 'Add') : t('search_condition.dialog.submit_update', 'Update')}
                </Button>
                </div>
            </>
        )}
    >
        {loaded ? <>
            {textQueryMode ? <>
                    <AqlField
                        error={!!error}
                        value={query}
                        onChange={setQuery}
                    />
                </> :

                <ConditionsBuilder
                    definitionsIndex={definitionsIndex}
                    expression={expression}
                    setExpression={setExpression}
                />
            }

            {error ? <Alert severity={'error'}>{nl2br(error)}</Alert> : null}
        </> : <CircularProgress/>}
    </AppDialog>
}
