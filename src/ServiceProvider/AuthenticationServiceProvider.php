<?php

declare(strict_types=1);

namespace Mitra\ServiceProvider;

use Mitra\Authentication\TokenProvider;
use Mitra\Repository\InternalUserRepository;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

final class AuthenticationServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container[TokenProvider::class] = static function () use ($container): TokenProvider {
            return new TokenProvider($container[InternalUserRepository::class], $container['jwt.secret']);
        };
    }
}
