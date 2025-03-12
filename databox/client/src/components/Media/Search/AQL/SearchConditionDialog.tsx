import {AQLQuery} from "./query.ts";
import {Alert, Button} from "@mui/material";
import {useTranslation} from 'react-i18next';
import CheckIcon from "@mui/icons-material/Check";
import {StackedModalProps, useModals} from "@alchemy/navigation";
import React from "react";
import AqlField from "./AQLField.tsx";
import {AppDialog} from '@alchemy/phrasea-ui';
import {parseAQLQuery} from "./AQL.ts";
import nl2br from "react-nl2br";

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
    const [error, setError] = React.useState<string | undefined>();

    const setQuery = (q: string) => {
        if (error) {
            validate(q);
        }
        __setQuery(q);
    }

    const isNew = !condition.query;

    const validate = (q: string): boolean => {
        try {
            parseAQLQuery(q, true);
            setError(undefined);

            return true;
        } catch (e: any) {
            const error = e.message;
            setError(t('search_condition.dialog.error.invalid_query', {
                defaultValue: 'Invalid query: {{error}}',
                error,
            }));
        }

        return false;
    }

    return <AppDialog
        maxWidth={'md'}
        onClose={closeModal}
        title={isNew ? t('search_condition.dialog.edit_condition', 'Edit Condition') : t('search_condition.dialog.add_condition', 'Add Condition')}
        open={open}
        modalIndex={modalIndex}
        actions={({onClose}) => (
            <>
                <Button onClick={onClose}>
                    {t('dialog.cancel', 'Cancel')}
                </Button>
                <Button
                    startIcon={<CheckIcon/>}
                    onClick={() => {
                        if (validate(query)) {
                            closeModal();
                            onUpsert({
                                ...condition,
                                query,
                            });
                        }
                    }}
                    color={'primary'}
                    variant={'contained'}
                >
                    {isNew ? t('search_condition.dialog.submit_add', 'Add') : t('search_condition.dialog.submit_update', 'Update')}
                </Button>
            </>
        )}
    >
        <AqlField
            error={error}
            value={query}
            onChange={setQuery}
        />
        {error ? <Alert severity={'error'}>{nl2br(error)}</Alert> : null}
    </AppDialog>
}
