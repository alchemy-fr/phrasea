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
    <#assign paradeLogoUrl = (client.attributes.paradeLoginLogoUrl)!''>
    <#assign paradeLogoPosition = (client.attributes.paradeLoginLogoPosition)!'header-center'>
    <#assign paradeBackgroundUrl = (client.attributes.paradeLoginBackgroundUrl)!''>
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
    <#assign paradeFormOpacityRaw = (client.attributes.paradeLoginFormOpacity)!''>
    <#assign paradeFormOpacity = 100>
    <#if paradeFormOpacityRaw?matches('^[0-9]+$')>
        <#assign paradeFormOpacity = paradeFormOpacityRaw?number>
    </#if>
    <#assign paradeLogoScaleRaw = (client.attributes.paradeLoginLogoScale)!''>
    <#assign paradeLogoScale = 1>
    <#if paradeLogoScaleRaw?matches('^[0-9]+$')>
        <#assign paradeLogoScaleValue = paradeLogoScaleRaw?number>
        <#if paradeLogoScaleValue lt 50><#assign paradeLogoScaleValue = 50></#if>
        <#if paradeLogoScaleValue gt 200><#assign paradeLogoScaleValue = 200></#if>
        <#assign paradeLogoScale = paradeLogoScaleValue / 100>
    </#if>
    <#assign paradeFormTheme = (client.attributes.paradeLoginFormTheme)!'light'>
    <#assign paradeFormBaseRgb = '255, 255, 255'>
    <#assign paradeFormTextColor = '#1a1a1a'>
    <#if paradeFormTheme == 'dark'>
        <#assign paradeFormBaseRgb = '20, 20, 24'>
        <#assign paradeFormTextColor = '#f5f5f5'>
    <#elseif paradeFormTheme == 'gray'>
        <#assign paradeFormBaseRgb = '90, 90, 96'>
        <#assign paradeFormTextColor = '#fafafa'>
    </#if>
    <#if paradeLogoUrl?has_content || paradeBackgroundUrl?has_content || paradeBackgroundColor?has_content || paradeAccentColor?has_content || paradeRadius?has_content || paradeFormPosition != 'center' || paradeFormOpacity != 100 || paradeFormTheme != 'light'>
        <style>
            <#if paradeBackgroundUrl?has_content>
            .login-pf {
                background-image: ${paradeOverlayGradient}url('${paradeBackgroundUrl}');
                background-repeat: no-repeat;
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
            }
            .login-pf body {
                background: transparent;
            }
            <#elseif paradeBackgroundColor?has_content>
            .login-pf {
                background: ${paradeBackgroundColor};
                background-attachment: fixed;
            }
            .login-pf body {
                background: transparent;
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
                background-size: contain;
                min-height: clamp(${(2 * paradeLogoScale)?c}rem, ${(9 * paradeLogoScale)?c}vh, ${(4 * paradeLogoScale)?c}rem);
                font-size: 0;
                margin-top: 24px;
                margin-bottom: 24px;
            }
            </#if>
            <#if paradeLogoUrl?has_content>
            #kc-header-wrapper {
                font-size: 0;
                line-height: 0;
            }
            #kc-header-wrapper > * {
                display: none;
            }
            <#if paradeLogoPosition == 'form-center'>
            #kc-header-wrapper {
                background: none;
                min-height: 0;
                padding: 24px 0 0;
            }
            </#if>
            .parade-login-form-logo {
                max-height: clamp(${(1.75 * paradeLogoScale)?c}rem, ${(8 * paradeLogoScale)?c}vh, ${(3.5 * paradeLogoScale)?c}rem);
                max-width: min(${(60 * paradeLogoScale)?c}%, 100%);
            }
            </#if>
            .login-pf-page .card-pf {
                position: relative;
            }
            .login-pf-page .card-pf #kc-locale {
                position: absolute;
                top: 12px;
                right: 16px;
                margin: 0;
                z-index: 2;
            }
            .login-pf-page .card-pf #kc-locale ul {
                left: auto;
                right: 0;
            }
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
            <#if paradeFormOpacity lt 100 || paradeFormTheme != 'light'>
            .card-pf {
                background: rgba(${paradeFormBaseRgb}, ${paradeFormOpacity / 100});
                <#if paradeFormOpacity lt 100>
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                </#if>
                color: ${paradeFormTextColor};
            }
            </#if>
            <#if paradeFormTextColor?has_content>
            .login-pf-page .card-pf :is(#kc-page-title, #kc-attempted-username, #kc-info, #kc-info-wrapper, #kc-current-locale-link, p, span, div, label, h1, h2, li, small):not(a *, button *, [class*="alert"] *, #kc-locale ul *, .required) {
                color: ${paradeFormTextColor};
            }
            .login-pf-page .card-pf #kc-locale ul li,
            .login-pf-page .card-pf #kc-locale ul li a {
                color: #1a1a1a;
            }
            .login-pf-page .card-pf #rememberMe {
                opacity: 1;
                accent-color: ${paradeAccentColor?has_content?then(paradeAccentColor, paradeFormTextColor)};
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
                    <div class="col-md-12">
                        <h1 id="kc-page-title"><#nested "header"></h1>
                    </div>
                    <div class="${properties.kcLabelWrapperClass!} subtitle">
                        <span class="subtitle"><span class="required">*</span> ${msg("requiredFields")}</span>
                    </div>
                </div>
            <#else>
                <h1 id="kc-page-title"><#nested "header"></h1>
            </#if>
        <#else>
            <#if displayRequiredFields>
                <div class="${properties.kcContentWrapperClass!}">
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
                    <div class="${properties.kcLabelWrapperClass!} subtitle">
                        <span class="subtitle"><span class="required">*</span> ${msg("requiredFields")}</span>
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
