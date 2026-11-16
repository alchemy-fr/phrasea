import 'server-only';
import {createCspReportHandler, cspReportOptionsFromEnv} from './cspReport';

const handler = createCspReportHandler(cspReportOptionsFromEnv(process.env));

/**
 * `POST` of the `CSP_REPORT_PATH` route of a client: relays the Content
 * Security Policy violation reports to the Sentry project of `SENTRY_DSN`,
 * behind the rate limits of the Sentry tunnel.
 */
export function POST(request: Request): Promise<Response> {
    return handler(request);
}
