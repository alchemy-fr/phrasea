import React, {Component} from 'react';
// import PropTypes from 'prop-types'

export default class UserInfo extends Component {
    render() {
        const {email} = this.props;

        return (
            <div>
                {email}
            </div>
        );
    }
}

// UserInfo.propTypes = {
//     email: PropTypes.string.isRequired,
// };

