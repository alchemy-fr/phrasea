import React from 'react';
import ReactDOM from 'react-dom';
import './scss/index.scss';
import ConfigWrapper from './component/ConfigWrapper';
import * as serviceWorker from './serviceWorker';
import './i18n/i18n';

ReactDOM.render(<ConfigWrapper />, document.getElementById('root'));

// If you want your app to work offline and load faster, you can change
// unregister() to register() below. Note this comes with some pitfalls.
// Learn more about service workers: https://bit.ly/CRA-PWA
serviceWorker.unregister();
