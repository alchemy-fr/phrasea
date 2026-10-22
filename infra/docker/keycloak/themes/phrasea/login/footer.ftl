<#macro content>
    <#if properties['env.KC_LOGIN_DISPLAY_DASHBOARD_LINK'] == 'true' >
        <div style="text-align:center; margin-top:30px;">
            <hr style="opacity:1;">
            <a href="${properties['env.DASHBOARD_CLIENT_URL']}"> Dashboard <a/> 
        </div>
    </#if>
</#macro>
