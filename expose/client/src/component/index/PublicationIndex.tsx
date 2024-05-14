import React from 'react';
import {Link} from '@alchemy/navigation';
import Description from '../layouts/shared-components/Description';
import moment from 'moment';
import SortImg from '../../images/sort.svg?react';
import {Dropdown, DropdownButton} from 'react-bootstrap';
import {Logo} from '../Logo';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getThumbPlaceholder} from '../layouts/shared-components/placeholders';
import apiClient from '../../lib/api-client';
import {useTranslation} from 'react-i18next';
import {Publication} from '../../types.ts';
import {getTranslatedDescription, getTranslatedTitle} from '../../i18n.ts';

enum SortBy {
    Date = 'date',
    Name = 'name',
}

type Props = {};

export default function PublicationIndex({}: Props) {
    const [data, setData] = React.useState<Publication[]>();
    const [sortBy, setSortBy] = React.useState<SortBy>(SortBy.Date);
    const {t} = useTranslation();

    const orders = {
        [SortBy.Date]: {
            label: t('order.last_post_added', 'Recents'),
            query: `order[createdAt]=desc`,
        },
        [SortBy.Name]: {
            label: t('order.publication_name', 'Name'),
            query: `order[title]=asc`,
        },
    };

    React.useEffect(() => {
        apiClient.get(`/publications?${orders[sortBy].query}`).then(res => {
            setData(res.data['hydra:member']);
        });
    }, [sortBy]);

    return (
        <>
            <div className="container">
                <h1>
                    <Logo />
                </h1>
                <div className="filters">
                    <div className="sort">
                        <DropdownButton
                            id="dropdown-basic-button"
                            title={
                                <>
                                    <SortImg width={20} height={20} />
                                    {orders[sortBy].label}
                                </>
                            }
                        >
                            {Object.keys(orders).map(o => {
                                return (
                                    <Dropdown.Item
                                        key={o}
                                        onClick={() => setSortBy(o as SortBy)}
                                    >
                                        {orders[o as keyof typeof orders].label}
                                    </Dropdown.Item>
                                );
                            })}
                        </DropdownButton>
                    </div>
                </div>
                <div>
                    {data ? (
                        data.map((p: Publication) => (
                            <div className={'publication-item'} key={p.id}>
                                <Link to={`/${p.slug || p.id}`}>
                                    <div className="media">
                                        <img
                                            src={
                                                p.cover
                                                    ? p.cover.thumbUrl ||
                                                      getThumbPlaceholder(
                                                          p.cover.mimeType
                                                      )
                                                    : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARMAAAC3CAMAAAAGjUrGAAAAKlBMVEXg4OD////j4+Pb29v7+/vi4uLx8fHs7Oz39/f09PTa2tru7u7m5ubX19cF3ejnAAABRElEQVR4nO3Z27JDMBiAURFVVN//dXfp+ZC6Y0//tS4zphPflARVBQAAAAAAAAAAAAAAAAAAAAAAAADAj6i/23p6G+jSkn7rKa6tXUyS0mHrSa4rp9QuHNKlJq8yl//i1GS/cEgbtsm4Kx0StEme7ipd4ZCgTQ7zrbT7fOoxm+TL+vL58ondZLwPd/2tQ+wm99HTRu4WJWaTapyTtNdTz/Pe9holaJNcnxrsh+vYZbt/iRK0SVUdj8fnf8k9StgmDyMPD4VzlNBN5qU4Pz0nT1EiN2mmdSe/vDroQzdppsX4NUlKOXCT5ry9f3t3ErhJU3qfFLdJMUncJuUkYZvkchJNNJlo8u58P6l3JXXoPVtRxCZ9PX5Tx/u+4zvgu2H5e3E7LP/MbxmWLowhXBIAAAAAAAAAAAAAAAAAAAAAAAAAgF/1BxZSCIBLTls7AAAAAElFTkSuQmCC'
                                            }
                                            className="mr-3"
                                            alt={p.title}
                                        />
                                        <div className="media-body">
                                            <h5 className="mt-0">
                                                {getTranslatedTitle(p)}
                                            </h5>
                                            {p.date ? (
                                                <time>
                                                    {moment(p.date).format(
                                                        'LLLL'
                                                    )}
                                                </time>
                                            ) : (
                                                ''
                                            )}
                                            <Description
                                                descriptionHtml={getTranslatedDescription(
                                                    p
                                                )}
                                            />
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        ))
                    ) : (
                        <FullPageLoader backdrop={false} />
                    )}
                </div>
            </div>
        </>
    );
}
