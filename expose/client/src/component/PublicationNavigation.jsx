import React, {PureComponent} from 'react';
// import { PropTypes } from 'prop-types'
import {Link} from '@alchemy/navigation';
import {loadPublication} from './api';
import {getTranslatedTitle} from "../i18n";

class PublicationNavigation extends PureComponent {
    // static propTypes = {
    //     publication: PropTypes.object.isRequired,
    // }

    state = {
        loading: '',
    };

    static getDerivedStateFromProps(props, state) {
        if (!state.propsPub || props.publication.id !== state.propsPub.id) {
            return {
                loading: '',
                propsPub: props.publication,
            };
        }

        return null;
    }

    onSelect = id => {
        this.setState({loading: id});
    };

    render() {
        const {publication} = this.props;

        if (!publication.title) {
            return null;
        }

        const {loading} = this.state;
        const {parent} = publication;

        return (
            <div className={`pub-nav ${loading ? ' nav-loading' : ''}`}>
                {parent && (
                    <div className={'nav-parent'}>
                        <Link
                            className={`nav-item${
                                loading === parent.id ? ' nav-current' : ''
                            }`}
                            to={`/${parent.slug || parent.id}`}
                            onClick={this.onSelect.bind(this, parent.id)}
                        >
                            {getTranslatedTitle(parent)}
                        </Link>
                    </div>
                )}
                <NavTree
                    onSelect={this.onSelect}
                    loading={loading}
                    current={publication}
                    depth={1}
                    openChildren={true}
                    publications={parent ? parent.children : [publication]}
                />
            </div>
        );
    }
}

class NavTree extends PureComponent {
    // static propTypes = {
    //     publications: PropTypes.array.isRequired,
    //     current: PropTypes.object.isRequired,
    //     depth: PropTypes.number.isRequired,
    //     openChildren: PropTypes.bool,
    //     onSelect: PropTypes.func.isRequired,
    //     loading: PropTypes.string,
    // }

    state = {
        publicationChildren: {},
        openPublications: {},
    };

    toggle(id) {
        this.loadChildren(id);

        this.setState(prevState => {
            const openPublications = {...prevState.openPublications};

            if (undefined === openPublications[id]) {
                openPublications[id] = !(
                    this.props.openChildren && id === this.props.current.id
                );
            } else {
                openPublications[id] = !openPublications[id];
            }

            return {
                openPublications,
            };
        });
    }

    async loadChildren(id) {
        const res = await loadPublication(id);

        this.setState(prevState => {
            const publicationChildren = {...prevState.publicationChildren};

            publicationChildren[res.id] = res.children;

            return {publicationChildren};
        });
    }

    render() {
        const {current, publications, depth, openChildren, onSelect, loading} =
            this.props;
        const {openPublications, publicationChildren} = this.state;

        const baseNavClass = `nav-item nav-depth-${depth}`;

        return (
            <ul className="pub-nav-ul list-unstyled components">
                {publications.map(c => {
                    const p = typeof c === 'string' ? current : c;
                    const children = p.children || publicationChildren[p.id];

                    const isCurrent = loading
                        ? loading === p.id
                        : p.id === current.id;

                    const navClass = `${baseNavClass}${
                        isCurrent ? ' nav-current' : ''
                    }${p.childrenCount > 0 ? ' nav-has-children' : ''}`;

                    const displayChildren =
                        false !== openPublications[p.id] &&
                        p.childrenCount &&
                        (openPublications[p.id] ||
                            (openChildren && p.id === current.id));

                    return (
                        <li key={p.id}>
                            {isCurrent ? (
                                <div className={navClass}>{getTranslatedTitle(p)}</div>
                            ) : (
                                <Link
                                    onClick={() => onSelect(p.id)}
                                    className={navClass}
                                    to={`/${p.slug || p.id}`}
                                >
                                    {p.title}
                                </Link>
                            )}
                            {!!displayChildren && (
                                <>
                                    {children && children.length > 0 ? (
                                        <NavTree
                                            publications={children}
                                            current={current}
                                            loading={loading}
                                            depth={depth + 1}
                                            onSelect={onSelect}
                                        />
                                    ) : (
                                        <div className={navClass}>
                                            Loading...
                                        </div>
                                    )}
                                </>
                            )}
                            {p.childrenCount > 0 && (
                                <div
                                    className={'pub-nav-toggle'}
                                    onClick={this.toggle.bind(this, p.id)}
                                >
                                    {!!displayChildren ? '-' : '+'}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        );
    }
}

export default PublicationNavigation;
