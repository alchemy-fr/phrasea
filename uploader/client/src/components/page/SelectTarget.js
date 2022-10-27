import React, {useEffect, useState} from 'react';
import '../../scss/Upload.scss';
import {getTargets} from "../../requests";
import Container from "../Container";
import {Link, Redirect} from "react-router-dom";
import FullPageLoader from "../FullPageLoader";
import {Translation} from "react-i18next";

export default function SelectTarget() {
    const [targets, setTargets] = useState();

    useEffect(() => {
        getTargets().then(setTargets);
    }, []);

    if (!targets) {
        return <FullPageLoader/>
    }

    if (targets.length === 1) {
        return <Redirect to={`/upload/${targets[0].id}`}/>
    }

    return <Container>
        <div className={'row targets'}>
            {targets.length === 0 && <div>
                <Translation>
                    {t => t('targets.none_available', `You don't have access to any upload target.`)}
                </Translation>
            </div>}
            {targets.map(t => {
                return <Link
                    key={t.id}
                    to={`/upload/${t.id}`}
                    className={'col-md-6 col-sm-12 target'}
                >
                    <span className={'target-box'}>
                        <span className={'target-title'}>
                            {t.name}
                        </span>
                        {t.description && <p className={'target-desc'}>{t.description}</p>}
                    </span>
                </Link>
            })}
        </div>
    </Container>
}
