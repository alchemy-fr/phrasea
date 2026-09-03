<#--
  Parade override of the base Keycloak login template.

  Adds optional per-client login branding, driven by plain (non-nested)
  attributes on the Keycloak client — set them via the Admin REST API
  (`PUT /admin/realms/{realm}/clients/{id}`, `attributes: {...}`), the same
  way Parade's keycloak-bootstrap service already manages other client
  attributes. All of them are optional; when absent this renders exactly
  like the unmodified base template (same as every other client today).

    paradeLoginLogoUrl       - absolute URL, replaces the header logo image
    paradeLoginLogoPosition  - "header-center" (default) | "header-left" |
                                "header-right" | "form-center" (moves the
                                logo into the card itself, above the title,
                                instead of the page header)
    paradeLoginLogoSize      - integer (px), logo height
    paradeLoginBackgroundUrl - absolute URL, replaces the page background
    paradeLoginBackgroundOverlay - integer 0-100, darkens the background
                                    image by that percentage for legibility
                                    (ignored without a background URL)
    paradeLoginBackgroundColor - CSS color, replaces the page background
                                   with a solid color instead of an image
                                   (ignored when a background URL is set)
    paradeLoginAccentColor   - CSS color, replaces the fixed #0066cc accent
                                (card border, links, primary button)
    paradeLoginFormPosition  - horizontal alignment of the card on the page,
                                independent of the background (which always
                                covers the full page): "center" (default) |
                                "left" | "right"
    paradeLoginFormOpacity   - integer 20-100, opacity of the card itself
                                (below 100 adds a frosted-glass blur so text
                                stays legible over a background image)
    paradeLoginFormTheme     - base color/typography of the card: "light"
                                (default) | "gray" | "dark"
    paradeLoginFormPadding   - integer (px), internal padding applied
                                uniformly to the card (all four sides)
    paradeLoginFormWidth     - integer (px), max-width of the card (default
                                667, the base theme's own hardcoded value)
    paradeLoginFormScale     - integer 70-150, percentage scale applied to
                                the card's content via CSS zoom — the base
                                theme hardcodes font-size/line-height in px
                                on many individual selectors, so a uniform
                                zoom is the reliable way to resize all of
                                them (and their spacing) together
    paradeLoginRadius        - integer (px), corner radius applied to the
                                card and primary button
    paradeLoginWelcomeText   - plain text shown above the page title

  This is a first draft for discussion, not a final layout.
-->
<#import "footer.ftl" as loginFooter>
<#macro registrationLayout bodyClass="" displayInfo=false displayMessage=true displayRequiredFields=false>
<!DOCTYPE html>
<html class="${properties.kcHtmlClass!}" lang="${lang}"<#if realm.internationalizationEnabled> dir="${(locale.rtl)?then('rtl','ltr')}"</#if>>

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <#if properties.meta?has_content>
        <#list properties.meta?split(' ') as meta>
            <meta name="${meta?split('==')[0]}" content="${meta?split('==')[1]}"/>
        </#list>
    </#if>
    <title>${msg("loginTitle",(realm.displayName!''))}</title>
    <link rel="icon" href="${url.resourcesPath}/img/favicon.ico" />
    <#if properties.stylesCommon?has_content>
        <#list properties.stylesCommon?split(' ') as style>
            <link href="${url.resourcesCommonPath}/${style}" rel="stylesheet" />
        </#list>
    </#if>
    <#if properties.styles?has_content>
        <#list properties.styles?split(' ') as style>
            <link href="${url.resourcesPath}/${style}" rel="stylesheet" />
        </#list>
    </#if>
    <#-- Parade: per-client login branding override, see file header for the attribute contract. -->
    <#assign paradeLogoUrl = (client.attributes.paradeLoginLogoUrl)!''>
    <#assign paradeLogoPosition = (client.attributes.paradeLoginLogoPosition)!'header-center'>
    <#assign paradeLogoSize = (client.attributes.paradeLoginLogoSize)!''>
    <#assign paradeBackgroundUrl = (client.attributes.paradeLoginBackgroundUrl)!''>
    <#-- paradeLoginBackgroundOverlay is a 0-100 opacity percentage. Tolerates
         the legacy "true"/"false" boolean this attribute used to hold (from
         a client synced before this theme update, mapped the same way the
         Parade admin's own back-compat parsing does) instead of crashing. -->
    <#assign paradeBackgroundOverlayRaw = (client.attributes.paradeLoginBackgroundOverlay)!''>
    <#assign paradeBackgroundOverlay = 0>
    <#if paradeBackgroundOverlayRaw?matches('^[0-9]+$')>
        <#assign paradeBackgroundOverlay = paradeBackgroundOverlayRaw?number>
    <#elseif paradeBackgroundOverlayRaw == 'true'>
        <#assign paradeBackgroundOverlay = 35>
    </#if>
    <#assign paradeOverlayGradient = ''>
    <#if paradeBackgroundOverlay gt 0>
        <#assign paradeOverlayGradient = 'linear-gradient(rgba(0, 0, 0, ' + (paradeBackgroundOverlay / 100) + '), rgba(0, 0, 0, ' + (paradeBackgroundOverlay / 100) + ')), '>
    </#if>
    <#assign paradeBackgroundColor = (client.attributes.paradeLoginBackgroundColor)!''>
    <#assign paradeAccentColor = (client.attributes.paradeLoginAccentColor)!''>
    <#assign paradeFormPosition = (client.attributes.paradeLoginFormPosition)!'center'>
    <#assign paradeRadius = (client.attributes.paradeLoginRadius)!''>
    <#assign paradeWelcomeText = (client.attributes.paradeLoginWelcomeText)!''>
    <#-- paradeLoginFormOpacity is an integer 20-100; tolerate a missing/malformed value by falling back to fully opaque. -->
    <#assign paradeFormOpacityRaw = (client.attributes.paradeLoginFormOpacity)!''>
    <#assign paradeFormOpacity = 100>
    <#if paradeFormOpacityRaw?matches('^[0-9]+$')>
        <#assign paradeFormOpacity = paradeFormOpacityRaw?number>
    </#if>
    <#assign paradeFormTheme = (client.attributes.paradeLoginFormTheme)!'light'>
    <#assign paradeFormBaseRgb = '255, 255, 255'>
    <#assign paradeFormTextColor = ''>
    <#if paradeFormTheme == 'dark'>
        <#assign paradeFormBaseRgb = '20, 20, 24'>
        <#assign paradeFormTextColor = '#f5f5f5'>
    <#elseif paradeFormTheme == 'gray'>
        <#assign paradeFormBaseRgb = '90, 90, 96'>
        <#assign paradeFormTextColor = '#fafafa'>
    </#if>
    <#assign paradeFormPadding = (client.attributes.paradeLoginFormPadding)!''>
    <#assign paradeFormWidth = (client.attributes.paradeLoginFormWidth)!''>
    <#assign paradeFormScale = (client.attributes.paradeLoginFormScale)!''>
    <#if paradeLogoUrl?has_content || paradeBackgroundUrl?has_content || paradeBackgroundColor?has_content || paradeAccentColor?has_content || paradeRadius?has_content || paradeFormPosition != 'center' || paradeFormOpacity != 100 || paradeFormTheme != 'light' || paradeFormPadding?has_content || paradeFormWidth?has_content || paradeFormScale?has_content>
        <style>
            <#-- The background always covers the full page regardless of
                 formPosition, which only controls the card's own horizontal
                 alignment (see below) — the two are independent concerns. -->
            <#if paradeBackgroundUrl?has_content>
            .login-pf body {
                background-image: ${paradeOverlayGradient}url('${paradeBackgroundUrl}');
                background-repeat: no-repeat;
                background-size: cover;
                background-position: center;
            }
            <#elseif paradeBackgroundColor?has_content>
            .login-pf body {
                background: ${paradeBackgroundColor};
            }
            </#if>
            <#if paradeFormPosition == 'left'>
            .card-pf {
                margin-left: 6%;
                margin-right: auto;
            }
            <#elseif paradeFormPosition == 'right'>
            .card-pf {
                margin-right: 6%;
                margin-left: auto;
            }
            </#if>
            <#if paradeLogoUrl?has_content && paradeLogoPosition != 'form-center'>
            #kc-header-wrapper {
                background: url('${paradeLogoUrl}') no-repeat <#if paradeLogoPosition == 'header-left'>left 5%<#elseif paradeLogoPosition == 'header-right'>right 5%<#else>center</#if> center;
                <#if paradeLogoSize?has_content>
                background-size: auto ${paradeLogoSize}px;
                min-height: ${paradeLogoSize}px;
                </#if>
                <#-- Hides the realm display name text so only the logo shows. -->
                font-size: 0;
                <#-- Otherwise the logo sits flush against the very top of the
                     page, and — especially with a large logoSize — flush
                     against the card below it. -->
                margin-top: 24px;
                margin-bottom: 24px;
            }
            </#if>
            <#if paradeLogoUrl?has_content && paradeLogoPosition == 'form-center' && paradeLogoSize?has_content>
            .parade-login-form-logo {
                max-height: ${paradeLogoSize}px;
            }
            </#if>
            <#if paradeAccentColor?has_content>
            .card-pf {
                border-bottom-color: ${paradeAccentColor};
            }
            a,
            #kc-social-providers h4 {
                color: ${paradeAccentColor};
            }
            .fa-info-circle {
                color: ${paradeAccentColor};
            }
            .pf-c-button.pf-m-primary {
                background-color: ${paradeAccentColor};
                border-color: ${paradeAccentColor};
            }
            </#if>
            <#if paradeRadius?has_content>
            .card-pf,
            .pf-c-button.pf-m-primary {
                border-radius: ${paradeRadius}px;
            }
            </#if>
            <#if paradeFormPadding?has_content>
            .card-pf {
                padding: ${paradeFormPadding}px;
            }
            </#if>
            <#if paradeFormWidth?has_content>
            .card-pf {
                max-width: ${paradeFormWidth}px;
            }
            </#if>
            <#if paradeFormScale?has_content>
            <#-- The base theme hardcodes font-size/line-height in px across
                 many individual selectors — zoom scales all of them (and
                 their spacing) together instead of overriding each one. -->
            .card-pf {
                zoom: ${paradeFormScale}%;
            }
            </#if>
            <#if paradeFormOpacity lt 100 || paradeFormTheme != 'light'>
            <#-- Below 100 the card becomes translucent, so a blur keeps its
                 text legible over a busy background image. -->
            .card-pf {
                background: rgba(${paradeFormBaseRgb}, ${paradeFormOpacity / 100});
                <#if paradeFormOpacity lt 100>
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                </#if>
                <#if paradeFormTextColor?has_content>
                color: ${paradeFormTextColor};
                </#if>
            }
            <#if paradeFormTextColor?has_content>
            .card-pf #kc-page-title,
            .card-pf .pf-c-form__label,
            .card-pf label,
            .card-pf .checkbox label,
            .card-pf p,
            .card-pf li {
                color: ${paradeFormTextColor};
            }
            </#if>
            </#if>
        </style>
    </#if>
    <#if properties.scripts?has_content>
        <#list properties.scripts?split(' ') as script>
            <script src="${url.resourcesPath}/${script}" type="text/javascript"></script>
        </#list>
    </#if>
    <script type="importmap">
        {
            "imports": {
                "rfc4648": "${url.resourcesCommonPath}/vendor/rfc4648/rfc4648.js"
            }
        }
    </script>
    <script src="${url.resourcesPath}/js/menu-button-links.js" type="module"></script>
    <#if scripts??>
        <#list scripts as script>
            <script src="${script}" type="text/javascript"></script>
        </#list>
    </#if>
    <script type="module">
        import { startSessionPolling } from "${url.resourcesPath}/js/authChecker.js";

        startSessionPolling(
            "${url.ssoLoginInOtherTabsUrl?no_esc}"
        );
    </script>
    <script type="module">
        document.addEventListener("click", (event) => {
            const link = event.target.closest("a[data-once-link]");

            if (!link) {
                return;
            }

            if (link.getAttribute("aria-disabled") === "true") {
                event.preventDefault();
                return;
            }

            const { disabledClass } = link.dataset;

            if (disabledClass) {
                link.classList.add(...disabledClass.trim().split(/\s+/));
            }

            link.setAttribute("role", "link");
            link.setAttribute("aria-disabled", "true");
        });
    </script>
    <#if authenticationSession??>
        <script type="module">
            import { checkAuthSession } from "${url.resourcesPath}/js/authChecker.js";

            checkAuthSession(
                "${authenticationSession.authSessionIdHash}"
            );
        </script>
    </#if>
</head>

<body class="${properties.kcBodyClass!}" data-page-id="login-${pageId}">
<div class="${properties.kcLoginClass!}">
    <div id="kc-header" class="${properties.kcHeaderClass!}">
        <div id="kc-header-wrapper"
             class="${properties.kcHeaderWrapperClass!}">${kcSanitize(msg("loginTitleHtml",(realm.displayNameHtml!'')))?no_esc}</div>
    </div>
    <div class="${properties.kcFormCardClass!}">
        <header class="${properties.kcFormHeaderClass!}">
            <#if paradeLogoUrl?has_content && paradeLogoPosition == 'form-center'>
                <img class="parade-login-form-logo" src="${paradeLogoUrl}" alt="" />
            </#if>
            <#if paradeWelcomeText?has_content>
                <p class="parade-login-welcome">${kcSanitize(paradeWelcomeText)?no_esc}</p>
            </#if>
            <#if realm.internationalizationEnabled  && locale.supported?size gt 1>
                <div class="${properties.kcLocaleMainClass!}" id="kc-locale">
                    <div id="kc-locale-wrapper" class="${properties.kcLocaleWrapperClass!}">
                        <div id="kc-locale-dropdown" class="menu-button-links ${properties.kcLocaleDropDownClass!}">
                            <button tabindex="1" id="kc-current-locale-link" aria-label="${msg("languages")}" aria-haspopup="true" aria-expanded="false" aria-controls="language-switch1">${locale.current}</button>
                            <ul role="menu" tabindex="-1" aria-labelledby="kc-current-locale-link" aria-activedescendant="" id="language-switch1" class="${properties.kcLocaleListClass!}">
                                <#assign i = 1>
                                <#list locale.supported as l>
                                    <li class="${properties.kcLocaleListItemClass!}" role="none">
                                        <a role="menuitem" id="language-${i}" class="${properties.kcLocaleItemClass!}" href="${l.url}">${l.label}</a>
                                    </li>
                                    <#assign i++>
                                </#list>
                            </ul>
                        </div>
                    </div>
                </div>
            </#if>
        <#if !(auth?has_content && auth.showUsername() && !auth.showResetCredentials())>
            <#if displayRequiredFields>
                <div class="${properties.kcContentWrapperClass!}">
                    <div class="${properties.kcLabelWrapperClass!} subtitle">
                        <span class="subtitle"><span class="required">*</span> ${msg("requiredFields")}</span>
                    </div>
                    <div class="col-md-10">
                        <h1 id="kc-page-title"><#nested "header"></h1>
                    </div>
                </div>
            <#else>
                <h1 id="kc-page-title"><#nested "header"></h1>
            </#if>
        <#else>
            <#if displayRequiredFields>
                <div class="${properties.kcContentWrapperClass!}">
                    <div class="${properties.kcLabelWrapperClass!} subtitle">
                        <span class="subtitle"><span class="required">*</span> ${msg("requiredFields")}</span>
                    </div>
                    <div class="col-md-10">
                        <#nested "show-username">
                        <div id="kc-username" class="${properties.kcFormGroupClass!}">
                            <label id="kc-attempted-username">${auth.attemptedUsername}</label>
                            <a id="reset-login" href="${url.loginRestartFlowUrl}" aria-label="${msg("restartLoginTooltip")}">
                                <div class="kc-login-tooltip">
                                    <i class="${properties.kcResetFlowIcon!}"></i>
                                    <span class="kc-tooltip-text">${msg("restartLoginTooltip")}</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            <#else>
                <#nested "show-username">
                <div id="kc-username" class="${properties.kcFormGroupClass!}">
                    <label id="kc-attempted-username">${auth.attemptedUsername}</label>
                    <a id="reset-login" href="${url.loginRestartFlowUrl}" aria-label="${msg("restartLoginTooltip")}">
                        <div class="kc-login-tooltip">
                            <i class="${properties.kcResetFlowIcon!}"></i>
                            <span class="kc-tooltip-text">${msg("restartLoginTooltip")}</span>
                        </div>
                    </a>
                </div>
            </#if>
        </#if>
      </header>
      <div id="kc-content">
        <div id="kc-content-wrapper">

          <#-- App-initiated actions should not see warning messages about the need to complete the action -->
          <#-- during login.                                                                               -->
          <#if displayMessage && message?has_content && (message.type != 'warning' || !isAppInitiatedAction??)>
              <div class="alert-${message.type} ${properties.kcAlertClass!} pf-m-<#if message.type = 'error'>danger<#else>${message.type}</#if>">
                  <div class="pf-c-alert__icon">
                      <#if message.type = 'success'><span class="${properties.kcFeedbackSuccessIcon!}"></span></#if>
                      <#if message.type = 'warning'><span class="${properties.kcFeedbackWarningIcon!}"></span></#if>
                      <#if message.type = 'error'><span class="${properties.kcFeedbackErrorIcon!}"></span></#if>
                      <#if message.type = 'info'><span class="${properties.kcFeedbackInfoIcon!}"></span></#if>
                  </div>
                      <span class="${properties.kcAlertTitleClass!}">${kcSanitize(message.summary)?no_esc}</span>
              </div>
          </#if>

          <#nested "form">

          <#if auth?has_content && auth.showTryAnotherWayLink()>
              <form id="kc-select-try-another-way-form" action="${url.loginAction}" method="post">
                  <div class="${properties.kcFormGroupClass!}">
                      <input type="hidden" name="tryAnotherWay" value="on"/>
                      <a href="#" id="try-another-way"
                         onclick="document.forms['kc-select-try-another-way-form'].requestSubmit();return false;">${msg("doTryAnotherWay")}</a>
                  </div>
              </form>
          </#if>

          <#nested "socialProviders">

          <#if displayInfo>
              <div id="kc-info" class="${properties.kcSignUpClass!}">
                  <div id="kc-info-wrapper" class="${properties.kcInfoAreaWrapperClass!}">
                      <#nested "info">
                  </div>
              </div>
          </#if>
        </div>
      </div>

      <@loginFooter.content/>
    </div>
  </div>
</body>
</html>
</#macro>
