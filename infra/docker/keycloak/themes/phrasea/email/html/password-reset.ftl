<#import "template.ftl" as layout>
<@layout.emailLayout>
${kcSanitize(msg("passwordResetBodyHtml", link, linkExpiration, realmName, linkExpirationFormatter(linkExpiration), properties["env.dashboardClientUrl"]))?no_esc}
</@layout.emailLayout>
