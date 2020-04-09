import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";

class PublicationNavigation extends PureComponent {
    static propTypes = {
        parent: PropTypes.object,
        currentTitle: PropTypes.string.isRequired,
        children: PropTypes.array.isRequired,
    };

    render() {
        const {parent, children, currentTitle} = this.props;

        return <>
            {parent ? <Link to={`/${parent.id}`}>
                {parent.title}
            </Link> : ''}
            <h2>{currentTitle}</h2>
            <NavTree children={children}/>
        </>
    }
}

class NavTree extends PureComponent {
    static propTypes = {
        children: PropTypes.array.isRequired,
    };

    render() {
        return <ul className="list-unstyled components">
            {this.props.children.map(c => {
                console.log('c', c);
                return <li
                key={c.id}
            >
                <Link to={`/${c.id}`}>
                    {c.title}
                </Link>
                {c.children && c.children.length > 0 ?
                    <NavTree children={c.children}/>
                    : ''}
            </li>
            })}
        </ul>
    }
}

export default PublicationNavigation;
