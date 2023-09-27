(function (config, env) {
    config = config || {};

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
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.EXPOSE_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL ? parseInt(env.S3_REQUEST_SIGNATURE_TTL) : 86400,
        disableIndexPage: ['true', '1', 'on'].includes(env.DISABLE_INDEX_PAGE),
    };
});
