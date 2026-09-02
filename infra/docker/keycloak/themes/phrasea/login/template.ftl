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
    paradeLoginBackgroundUrl - absolute URL, replaces the page background
    paradeLoginBackgroundOverlay - "true" to darken the background image
                                    for legibility (ignored without a
                                    background URL)
    paradeLoginBackgroundColor - CSS color, replaces the page background
                                   with a solid color instead of an image
                                   (ignored when a background URL is set)
    paradeLoginAccentColor   - CSS color, replaces the fixed #0066cc accent
                                (card border, links, primary button)
    paradeLoginFormPosition  - "centered" (default) | "split-left" |
                                "split-right" | "overlay"
    paradeLoginRadius        - integer (px), corner radius applied to the
                                card and primary button
    paradeLoginWelcomeText   - plain text shown above the page title

  This is a first draft for discussion, not a final layout: the
  split-left/split-right/overlay positions are a CSS-only approximation and
  worth eyeballing live before relying on them.
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
    <#assign paradeBackgroundUrl = (client.attributes.paradeLoginBackgroundUrl)!''>
    <#assign paradeBackgroundOverlay = (client.attributes.paradeLoginBackgroundOverlay)!''>
    <#assign paradeBackgroundColor = (client.attributes.paradeLoginBackgroundColor)!''>
    <#assign paradeAccentColor = (client.attributes.paradeLoginAccentColor)!''>
    <#assign paradeFormPosition = (client.attributes.paradeLoginFormPosition)!'centered'>
    <#assign paradeRadius = (client.attributes.paradeLoginRadius)!''>
    <#assign paradeWelcomeText = (client.attributes.paradeLoginWelcomeText)!''>
    <#if paradeLogoUrl?has_content || paradeBackgroundUrl?has_content || paradeBackgroundColor?has_content || paradeAccentColor?has_content || paradeRadius?has_content>
        <style>
            <#if paradeBackgroundUrl?has_content>
            .login-pf body {
                background-image: <#if paradeBackgroundOverlay == 'true'>linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)), </#if>url('${paradeBackgroundUrl}');
                background-repeat: no-repeat;
                <#if paradeFormPosition == 'split-left'>
                background-size: 50% 100%;
                background-position: left center;
                <#elseif paradeFormPosition == 'split-right'>
                background-size: 50% 100%;
                background-position: right center;
                <#else>
                background-size: cover;
                background-position: center;
                </#if>
            }
            <#elseif paradeBackgroundColor?has_content>
            .login-pf body {
                background: ${paradeBackgroundColor};
            }
            </#if>
            <#if (paradeBackgroundUrl?has_content || paradeBackgroundColor?has_content) && (paradeFormPosition == 'split-left' || paradeFormPosition == 'split-right')>
            .login-pf-page {
                display: flex;
                align-items: center;
                <#if paradeFormPosition == 'split-left'>
                justify-content: flex-end;
                padding-right: 6%;
                <#else>
                justify-content: flex-start;
                padding-left: 6%;
                </#if>
            }
            </#if>
            <#if (paradeBackgroundUrl?has_content || paradeBackgroundColor?has_content) && paradeFormPosition == 'overlay'>
            .card-pf {
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(6px);
            }
            </#if>
            <#if paradeLogoUrl?has_content && paradeLogoPosition != 'form-center'>
            #kc-header-wrapper {
                background: url('${paradeLogoUrl}') no-repeat <#if paradeLogoPosition == 'header-left'>left<#elseif paradeLogoPosition == 'header-right'>right<#else>center</#if> center;
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
