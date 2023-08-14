package com.phrasea.keycloak;

import com.arakelian.jq.*;
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

public class JqGroupMapper extends AbstractIdentityProviderMapper {

    private static final Logger LOG = Logger.getLogger(JqGroupMapper.class);

    protected static final List<ProviderConfigProperty> configProperties = new ArrayList<>();
    public static final String JQ_FILTER = "jq_filter";

    static {
        ProviderConfigProperty property;
        property = new ProviderConfigProperty();
        property.setName(JQ_FILTER);
        property.setLabel("JQ Filter");
        property.setHelpText("JQ filter which returns a list of group names");
        property.setType(ProviderConfigProperty.STRING_TYPE);
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
    }

    @Override
    public void importNewUser(KeycloakSession session, RealmModel realm, UserModel user, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        this.mapGroups(realm, user, mapperModel, context);
    }

    @Override
    public void updateBrokeredUser(KeycloakSession session, RealmModel realm, UserModel user, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        this.mapGroups(realm, user, mapperModel, context);
    }

    private void mapGroups(RealmModel realm, UserModel user, IdentityProviderMapperModel mapperModel, BrokeredIdentityContext context) {
        JsonNode profileJsonNode = (JsonNode)context.getContextData().get("UserInfo");
        LOG.warnf("profileJsonNode: '%s'", profileJsonNode.toString());
        LOG.warnf("Context data: '%s'", context.getContextData().toString());

        String jqFilter = mapperModel.getConfig().get(JQ_FILTER);
        LOG.warnf("JQ filter: '%s'", jqFilter);

        JqLibrary library = ImmutableJqLibrary.of();
        final JqRequest request = ImmutableJqRequest.builder()
            .lib(library)
            .input(profileJsonNode.toString())
            .filter(jqFilter)
            .build();

        final JqResponse response = request.execute();
        if (response.hasErrors()) {
            LOG.errorf("JQ filter error: %s", response.getErrors().toString());
        } else {
            LOG.warnf("JQ output: '%s'", response.getOutput());
        }
    }

//    private GroupModel getGroup(RealmModel realm, String groupPath, IdentityProviderMapperModel mapperModel) {
//        GroupModel group = KeycloakModelUtils.findGroupByPath(realm, groupPath);
//        if (group == null) {
//            LOG.warnf("Unable to find group by path '%s' referenced by mapper '%s' on realm '%s'.", groupPath, mapperModel.getName(), realm.getName());
//        }
//
//        return group;
//    }

    public String getHelpText() {
        return "Add User to a list of Groups coming from the result of the jq filter of the userinfo response.";
    }
}
