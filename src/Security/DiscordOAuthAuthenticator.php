<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordOAuthAuthenticator extends OAuth2Authenticator
{
    private const EXTERNAL_ACCOUNT_KIND = 'discord';

    private ClientRegistry $clientRegistry;
    private UserManager $userManager;
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;

    public function __construct(ClientRegistry $clientRegistry, UserManager $userManager, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler)
    {
        $this->clientRegistry = $clientRegistry;
        $this->userManager = $userManager;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'login_discord';
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request): Passport
    {
        if (!$request->query->has('state')) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        /** @var DiscordResourceOwner $resourceOwner */
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        /** @var array{id: string, username: string, discriminator: string} $userInfo */
        $userInfo = $resourceOwner->toArray();

        $displayName = sprintf('%s#%s', $userInfo['username'], $userInfo['discriminator']);

        $user = $this->userManager->findOrCreateUserForExternalAccount(
            self::EXTERNAL_ACCOUNT_KIND, $userInfo['id'], $displayName, $userInfo['username']);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), function () use ($user) {
            return $user;
        }), [
            new RememberMeBadge(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof AuthenticationCredentialsNotFoundException) {
            return $this->clientRegistry
                ->getClient('discord')
                ->redirect(['identify'], []);
        }

        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }
}
