<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\Security\ResourceAccessCheckerInterface;
use ApiPlatform\Util\OperationRequestInitiatorTrait;
use ApiPlatform\Util\RequestAttributesExtractor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onSecurity', priority: 3)]
#[AsEventListener(event: KernelEvents::VIEW, method: 'onSecurityPostDenormalize', priority: 33)]
#[AsEventListener(event: KernelEvents::VIEW, method: 'onSecurityPostValidation', priority: 10)]
final class OverriddenDenyAccessListener
{
    use OperationRequestInitiatorTrait;

    public function __construct(ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null, private readonly ?ResourceAccessCheckerInterface $resourceAccessChecker = null)
    {
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    public function onSecurity(RequestEvent $event): void
    {
        $this->checkSecurity($event->getRequest(), 'security');
    }

    public function onSecurityPostDenormalize(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $this->checkSecurity($request, 'security_post_denormalize', [
            'previous_object' => $request->attributes->get('previous_data'),
        ]);
    }

    public function onSecurityPostValidation(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $this->checkSecurity($request, 'security_post_validation', [
            'previous_object' => $request->attributes->get('previous_data'),
        ]);
    }

    /**
     * @throws AccessDeniedException
     */
    private function checkSecurity(Request $request, string $attribute, array $extraVariables = []): void
    {
        if (!$this->resourceAccessChecker || !$attributes = RequestAttributesExtractor::extractAttributes($request)) {
            return;
        }

        $operation = $this->initializeOperation($request);
        if (!$operation) {
            return;
        }

        switch ($attribute) {
            case 'security_post_denormalize':
                $isGranted = $operation->getSecurityPostDenormalize();
                $message = $operation->getSecurityPostDenormalizeMessage();
                break;
            case 'security_post_validation':
                $isGranted = $operation->getSecurityPostValidation();
                $message = $operation->getSecurityPostValidationMessage();
                break;
            default:
                $isGranted = $operation->getSecurity();
                $message = $operation->getSecurityMessage();
        }

        if (null === $isGranted) {
            return;
        }

        $extraVariables += $request->attributes->all();
        $extraVariables['object'] = $request->attributes->get('data');
        $extraVariables['previous_object'] = $request->attributes->get('previous_data');
        $extraVariables['request'] = $request;

        if (!$this->resourceAccessChecker->isGranted($attributes['resource_class'], $isGranted, $extraVariables)) {
            throw new AccessDeniedException($message ?? 'Access Denied.');
        }
    }
}
