(function (config, env) {
    config = config || {};

    const identityProviders = config.auth && config.auth.identity_providers ? config.auth.identity_providers.map(idp => {
        delete idp.options;

        return idp;
    }) : [];

    let scriptTpl = '';
    const analytics = config.databox.analytics;

    if (analytics) {
        switch (analytics.provider) {
            case 'matomo':
                scriptTpl = `
<!-- Matomo -->
<script type="text/javascript">
  var _paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//{host}/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '{siteId}']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
`
                    .replace('{host}', analytics.options.host)
                    .replace('{siteId}', analytics.options.siteId)
                ;
                break;
            case 'google_analytics':
                    scriptTpl = `<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={propertyId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '{propertyId}');
</script>
`
                        .replace(/{propertyId}/g, analytics.options.propertyId)
                break;
            default:
                console.error(`Unsupported analytics provider ${analytics.provider}`);
        }
    }

    const normalizeTypes = (value) => {
        if (!value) {
            return {};
        }
        const v = value.trim();

        if (!v) {
            return {};
        }

        const types = [...v.matchAll(/([\w*]+\/[\w*+.-]+)(\([\w,]*\))?/g)];
        const struct = {};
        for (const t of types) {
            struct[t[1]] = t[2] ? t[2].substring(1, t[2].length - 1).split(',').map(e => e.trim()).filter(e => !!e) : [];
        }

        return struct;
    };

    return {
        customHTML: {
            __TPL_HEAD__: scriptTpl,
        },
        locales: config.available_locales,
        identityProviders,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.DATABOX_API_URL,
        uploaderApiBaseUrl: env.UPLOADER_API_URL,
        uploaderTargetSlug: env.UPLOADER_TARGET_SLUG,
        authBaseUrl: env.OPENID_CONNECT_URL,
        clientId: env.CLIENT_ID,
        devMode: env.DEV_MODE === 'true',
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL,
        displayServicesMenu: env.DISPLAY_SERVICES_MENU === 'true',
        dashboardBaseUrl: env.DASHBOARD_URL,
        allowedTypes: normalizeTypes(env.ALLOWED_FILE_TYPES),
    };
});
