import React, {useEffect, useState} from 'react';
import {getPath, useNavigate} from '@alchemy/navigation';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {routes} from '../routes.ts';
import {Alert, Container} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Target} from '../types.ts';
import {getTargets} from '../api/targetApi.ts';
import Grid from '@mui/material/Unstable_Grid2';
import TargetCard from '../components/TargetCard.tsx';

export default function TargetListPage() {
    const [targets, setTargets] = useState<Target[]>();
    const navigate = useNavigate();
    const {t} = useTranslation();

    useEffect(() => {
        getTargets().then(r => setTargets(r.result));
    }, []);

    useEffect(() => {
        if (targets && targets.length === 1) {
            navigate(getPath(routes.upload, {id: targets[0].id}));
        }
    }, [targets]);

    if (!targets || targets?.length === 1) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <Container>
            <div>
                {targets.length === 0 && (
                    <Alert severity={'warning'}>
                        {t(
                            'targets.none_available',
                            `You don't have access to any upload target.`
                        )}
                    </Alert>
                )}

                <div>
                    <Grid
                        container
                        spacing={2}
                        sx={{
                            '.MuiGrid2-root': {
                                'display': 'flex',
                                '> div': {
                                    display: 'flex',
                                    width: '100%',
                                    flexGrow: 1,
                                },
                                '.MuiCardActionArea-root': {
                                    display: 'flex',
                                    flexDirection: 'column',
                                    height: '100%',
                                    justifyContent: 'space-between',
                                },
                                '.MuiCardContent-root': {
                                    flexGrow: 1,
                                },
                            },
                        }}
                    >
                        {targets
                            ? targets.map((target: Target) => (
                                  <Grid xs={12} sm={6} md={4} key={target.id}>
                                      <TargetCard target={target} />
                                  </Grid>
                              ))
                            : null}
                    </Grid>
                </div>
            </div>
        </Container>
    );
}
