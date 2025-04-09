import {StateSetterHandler} from '../../../../../types.ts';
import {QBExpression} from './builderTypes.ts';
import {Button} from '@mui/material';
import {addExpression} from './builder.ts';
import AddIcon from '@mui/icons-material/Add';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {FlexRow} from '@alchemy/phrasea-ui';

type Props = {
    setExpression: StateSetterHandler<QBExpression>;
};

export default function AddExpressionRow({setExpression}: Props) {
    const {t} = useTranslation();

    return (
        <FlexRow
            sx={{
                mt: 2,
            }}
        >
            <Button
                onClick={() => {
                    setExpression(p => {
                        return addExpression(p, false);
                    });
                }}
                startIcon={<AddIcon />}
            >
                {t('search_condition.builder.add_condition', 'Add Condition')}
            </Button>
            <Button
                onClick={() => {
                    setExpression(p => {
                        return addExpression(p, true);
                    });
                }}
                startIcon={<AddIcon />}
            >
                {t(
                    'search_condition.builder.add_condition_group',
                    'Add Condition Group'
                )}
            </Button>
        </FlexRow>
    );
}
