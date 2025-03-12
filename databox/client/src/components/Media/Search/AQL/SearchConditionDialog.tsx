import {AQLQuery} from "./query.ts";
import {Button} from "@mui/material";
import {LoadingButton} from "@mui/lab";
import {useTranslation} from 'react-i18next';
import CheckIcon from "@mui/icons-material/Check";
import {StackedModalProps, useModals} from "@alchemy/navigation";
import React from "react";
import AqlField from "./AQLField.tsx";
import {AppDialog} from '@alchemy/phrasea-ui';

type Props = {
    condition: AQLQuery;
    onUpdate: (condition: AQLQuery) => void;
} & StackedModalProps;

export default function SearchConditionDialog({
    condition,
    open,
    modalIndex,
    onUpdate,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [query, setQuery] = React.useState(condition.query);

    return  <AppDialog
        maxWidth={'md'}
        onClose={closeModal}
        title={t('search_condition.dialog.edit_condition', 'Edit Condition')}
        open={open}
        modalIndex={modalIndex}
        actions={({onClose}) => (
            <>
                <Button onClick={onClose}>
                    {t('dialog.cancel', 'Cancel')}
                </Button>
                <LoadingButton
                    startIcon={<CheckIcon />}
                    onClick={() => onUpdate({
                        ...condition,
                        query,
                    })}
                    color={'primary'}
                    variant={'contained'}
                >
                    {t('search_condition.dialog.submit', 'Update')}
                </LoadingButton>
            </>
        )}
    >
        <AqlField value={query} onChange={setQuery}/>
    </AppDialog>
}
