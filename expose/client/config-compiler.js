(function (config, env) {
    config = config || {};

    const identityProviders = config.auth && config.auth.identity_providers ? config.auth.identity_providers.map(idp => {
        delete idp.options;
        delete idp.group_jq_normalizer;
        delete idp.group_map;

        return idp;
    }) : [];

    let scriptTpl = '';
    const analytics = config.expose.analytics;

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

    return {
        customHTML: {
            __TPL_HEAD__: scriptTpl,
        },
        locales: config.available_locales,
        identityProviders,
        loginFormLayout: config.auth.loginFormLayout,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.EXPOSE_API_BASE_URL,
        authBaseUrl: env.AUTH_API_BASE_URL,
        clientId: env.CLIENT_ID + '_' + env.CLIENT_RANDOM_ID,
        clientSecret: env.CLIENT_SECRET,
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL,
        disableIndexPage: ['true', '1', 'on'].includes(env.DISABLE_INDEX_PAGE),
    };
});
