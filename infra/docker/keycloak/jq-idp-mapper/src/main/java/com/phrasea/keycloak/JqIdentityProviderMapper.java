package com.phrasea.keycloak;

import com.fasterxml.jackson.databind.JsonNode;
import org.jboss.logging.Logger;
import org.keycloak.broker.oidc.KeycloakOIDCIdentityProviderFactory;
import org.keycloak.broker.oidc.OIDCIdentityProviderFactory;
import org.keycloak.broker.provider.AbstractIdentityProviderMapper;
import org.keycloak.broker.provider.BrokeredIdentityContext;
import org.keycloak.models.*;
import org.keycloak.models.utils.KeycloakModelUtils;
import org.keycloak.provider.ProviderConfigProperty;

import java.util.*;

public class JqIdentityProviderMapper extends AbstractIdentityProviderMapper {

    private static final Logger LOG = Logger.getLogger(JqIdentityProviderMapper.class);

    protected static final List<ProviderConfigProperty> configProperties = new ArrayList<>();
    public static final String JQ_FILTER = "jq_filter";

    static {
        ProviderConfigProperty property;
        property = new ProviderConfigProperty();
        property.setName(JQ_FILTER);
        property.setLabel("JQ Filter");
        property.setHelpText("JQ filter which returns a list of group names");
        property.setType(ProviderConfigProperty.LIST_TYPE);
        configProperties.add(property);
    }

    public static final String[] COMPATIBLE_PROVIDERS = {KeycloakOIDCIdentityProviderFactory.PROVIDER_ID, OIDCIdentityProviderFactory.PROVIDER_ID};

    public static final String PROVIDER_ID = "jq-groups-idp-mapper";

    public List<ProviderConfigProperty> getConfigProperties() {
        return configProperties;
    }

    public String getId() {
        return PROVIDER_ID;
    }

    public String[] getCompatibleProviders() {
        return COMPATIBLE_PROVIDERS;
    }

    public String getDisplayCategory() {
        return "Group Importer";
    }

    public String getDisplayType() {
        return "JQ to Groups Importer";
    }


    @Override
    public void preprocessFederatedIdentity(KeycloakSession session, RealmModel realm, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        JsonNode profileJsonNode = (JsonNode)context.getContextData().get("UserInfo");

        LOG.warnf("profileJsonNode: '%s'", profileJsonNode.toString());
        LOG.warnf("Context data: '%s'", context.getContextData().toString());
    }

    @Override
    public void importNewUser(KeycloakSession session, RealmModel realm, UserModel user, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        GroupModel group = this.getGroup(realm, mapperModel);
        if (group != null) {
                user.joinGroup(group);
        }
    }

    @Override
    public void updateBrokeredUser(KeycloakSession session, RealmModel realm, UserModel user, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        GroupModel group = this.getGroup(realm, mapperModel);
        if (group != null) {
            String groupId = group.getId();
            if (!context.hasMapperAssignedGroup(groupId)) {
                context.addMapperAssignedGroup(groupId);
                user.joinGroup(group);
            }

        }
    }

    private GroupModel getGroup(RealmModel realm, IdentityProviderMapperModel mapperModel) {
        String groupPath = mapperModel.getConfig().get("group");
        GroupModel group = KeycloakModelUtils.findGroupByPath(realm, groupPath);
        if (group == null) {
            LOG.warnf("Unable to find group by path '%s' referenced by mapper '%s' on realm '%s'.", groupPath, mapperModel.getName(), realm.getName());
        }

        return group;
    }

    public String getHelpText() {
        return "Add User to a list of Groups coming from the result of the jq filter of the userinfo response.";
    }
}
