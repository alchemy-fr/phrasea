import {TargetParam} from '../../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import TargetParamForm from './TargetParamForm.tsx';
import {Container, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getTargetParam} from '../../api/targetParamApi.ts';
import {MenuClasses} from '@alchemy/phrasea-framework';

type Props = {
    id?: string;
};

export default function TargetParamEdit({id}: Props) {
    const [data, setData] = useState<TargetParam>();
    const {t} = useTranslation();

    useEffect(() => {
        if (id) {
            getTargetParam(id).then(schema => {
                setData(schema);
            });
        }
    }, [id]);

    if (!data && id) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <>
            <div className={MenuClasses.PageHeader}>
                <Container maxWidth={'xl'}>
                    <Typography
                        variant={'h1'}
                        sx={{
                            my: 2,
                        }}
                    >
                        {data
                            ? t(
                                  'target_param.edit.title',
                                  'Editing Target Params: {{name}}',
                                  {name: data.target.name}
                              )
                            : t(
                                  'target_param.create.title',
                                  'Creating New Target Params'
                              )}
                    </Typography>
                </Container>
            </div>
            <Container maxWidth={'xl'}>
                <TargetParamForm
                    data={
                        data || {
                            data: {
                                my_var: 'my_value',
                            },
                        }
                    }
                />
            </Container>
        </>
    );
}
