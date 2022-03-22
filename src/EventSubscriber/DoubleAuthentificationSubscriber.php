<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class DoubleAuthentificationSubscriber implements EventSubscriberInterface
{
    public const ROLE_2FA_SUCCEED = 'ROLE_2FA_SUCCEED';
    public const FIREWALL_NAME = 'main';

    private $router;
    private $tokenStorage;
    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!\in_array($route, ['app_security_authentification_protected'], true)) {
            return;
        }

        $currentToken = $this->tokenStorage->getToken();
        if (!$currentToken instanceof PostAuthenticationToken) {
            $response = new RedirectResponse($this->router->generate('app_login'));
            $event->setResponse($response);

            return;
        }

        if (null === $currentToken->getUser() || self::FIREWALL_NAME !== $currentToken->getFirewallName()) {
            return;
        }

        if ($this->hasRole($currentToken, self::ROLE_2FA_SUCCEED)) {
            return;
        }

        $response = new RedirectResponse($this->router->generate('app_security_setup_fa'));
        $event->setResponse($response);
    }

    private function hasRole(TokenInterface $token, string $role): bool
    {
        return \in_array($role, $token->getRoleNames(), true);
    }
}
