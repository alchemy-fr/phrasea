import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import ConfigWrapper from './component/ConfigWrapper';
import './i18n/i18n';
import AnalyticsProvider from './component/anaytics/AnalyticsProvider';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <AnalyticsProvider>
        <ConfigWrapper />
    </AnalyticsProvider>
);
