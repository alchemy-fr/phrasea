package com.phrasea.keycloak;

import com.arakelian.jq.*;
import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.jboss.logging.Logger;
import org.keycloak.broker.oidc.KeycloakOIDCIdentityProviderFactory;
import org.keycloak.broker.oidc.OIDCIdentityProviderFactory;
import org.keycloak.broker.provider.AbstractIdentityProviderMapper;
import org.keycloak.broker.provider.BrokeredIdentityContext;
import org.keycloak.models.*;
import org.keycloak.models.utils.KeycloakModelUtils;
import org.keycloak.provider.ProviderConfigProperty;

import java.util.*;
import java.util.stream.Collectors;

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
        LOG.infof("User data: %s", profileJsonNode.toString());

        String jqFilter = mapperModel.getConfig().get(JQ_FILTER);
        LOG.infof("JQ filter: %s", jqFilter);

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
            LOG.infof("JQ output: %s", response.getOutput());
            ObjectMapper mapper = new ObjectMapper();
            try {
                JsonNode groupsNode = mapper.readTree(response.getOutput()).get("groups");
                if (groupsNode.isArray()) {
                    for (final JsonNode gNode : groupsNode) {
                        String groupName = gNode.asText();
                        GroupModel group = this.getGroup(realm, groupName);
                        String groupId = group.getId();
                        if (!context.hasMapperAssignedGroup(groupId)) {
                            context.addMapperAssignedGroup(groupId);
                            user.joinGroup(group);
                        }
                    }

                    List<GroupModel> groupList = user.getGroupsStream().collect(Collectors.toList());
                    for (GroupModel g : groupList) {
                        if (!context.hasMapperAssignedGroup(g.getId())) {
                            user.leaveGroup(g);
                        }
                    }
                } else {
                    LOG.errorf("groups node is not an array: %s", groupsNode.asText());
                }
            } catch (JsonProcessingException e) {
                LOG.errorf("JSON parsing error: '%s'", e.toString());
            }
        }
    }

    private GroupModel getGroup(RealmModel realm, String groupPath) {
        GroupModel group = KeycloakModelUtils.findGroupByPath(realm, groupPath);
        if (group == null) {
            LOG.infof("Creating group '%s'", groupPath);

            return realm.createGroup(groupPath);
        }

        return group;
    }

    public String getHelpText() {
        return "Add User to a list of Groups coming from the result of the jq filter of the userinfo response.";
    }
}
