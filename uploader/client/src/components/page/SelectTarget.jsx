import React, {useEffect, useState} from 'react';
import '../../scss/Upload.scss';
import {getTargets} from '../../requests';
import Container from '../Container';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import FullPageLoader from '../FullPageLoader';
import {Translation} from 'react-i18next';
import {routes} from "../../routes";

export default function SelectTarget() {
    const [targets, setTargets] = useState();
    const navigate = useNavigate();

    useEffect(() => {
        getTargets().then(setTargets);
    }, []);

    useEffect(() => {
        if (targets?.length === 1) {
            navigate(getPath(routes.upload, {id: targets[0].id}))
        }
    }, [targets]);

    if (!targets || targets?.length) {
        return <FullPageLoader/>;
    }

    return (
        <Container>
            <div className={'row targets'}>
                {targets.length === 0 && (
                    <div>
                        <Translation>
                            {t =>
                                t(
                                    'targets.none_available',
                                    `You don't have access to any upload target.`
                                )
                            }
                        </Translation>
                    </div>
                )}
                {targets.map(t => <Link
                    key={t.id}
                    to={`/upload/${t.id}`}
                    className={'col-md-6 col-sm-12 target'}
                >
                            <span className={'target-box'}>
                                <span className={'target-title'}>{t.name}</span>
                                {t.description && (
                                    <p className={'target-desc'}>
                                        {t.description}
                                    </p>
                                )}
                            </span>
                </Link>)}
            </div>
        </Container>
    );
}
