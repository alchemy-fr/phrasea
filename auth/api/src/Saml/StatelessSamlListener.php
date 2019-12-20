<?php

namespace App\Saml;

use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlToken;
use Hslavich\OneloginSamlBundle\Security\Firewall\SamlListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class StatelessSamlListener extends SamlListener
{
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @required
     */
    public function setRouter(UrlGeneratorInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * Performs authentication.
     *
     * @param Request $request A Request instance
     * @return TokenInterface|Response|null The authenticated token, null if full authentication is not possible, or a Response
     *
     * @throws AuthenticationException if the authentication fails
     * @throws \Exception if attribute set by "username_attribute" option not found
     */
    protected function attemptAuthentication(Request $request)
    {
        $this->oneLoginAuth->processResponse();
        if ($this->oneLoginAuth->getErrors()) {
            $this->logger->error($this->oneLoginAuth->getLastErrorReason());
            throw new AuthenticationException($this->oneLoginAuth->getLastErrorReason());
        }

        if (isset($this->options['use_attribute_friendly_name']) && $this->options['use_attribute_friendly_name']) {
            $attributes = $this->oneLoginAuth->getAttributesWithFriendlyName();
        } else {
            $attributes = $this->oneLoginAuth->getAttributes();
        }
        $attributes['sessionIndex'] = $this->oneLoginAuth->getSessionIndex();
        $token = new SamlToken();
        $token->setAttributes($attributes);

        if (isset($this->options['username_attribute'])) {
            if (!array_key_exists($this->options['username_attribute'], $attributes)) {
                $this->logger->error(sprintf("Found attributes: %s", print_r($attributes, true)));
                throw new \Exception(sprintf("Attribute '%s' not found in SAML data", $this->options['username_attribute']));
            }

            $username = $attributes[$this->options['username_attribute']][0];
        } else {
            $username = $this->oneLoginAuth->getNameId();
        }
        $token->setUser($username);

        return new RedirectResponse($request->request->get('RelayState'));
    }
}
