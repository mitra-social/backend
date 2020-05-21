<?php

declare(strict_types=1);

namespace Mitra\ServiceProvider;

use Mitra\ActivityPub\Client\ActivityPubClientInterface;
use Mitra\ActivityPub\Resolver\ExternalUserResolver;
use Mitra\CommandBus\CommandBusInterface;
use Mitra\CommandBus\EventBusInterface;
use Mitra\CommandBus\Handler\Command\ActivityPub\AssignActorCommandHandler;
use Mitra\CommandBus\Handler\Command\ActivityPub\FollowCommandHandler;
use Mitra\CommandBus\Handler\Command\ActivityPub\SendObjectToRecipientsCommandHandler;
use Mitra\CommandBus\Handler\Command\ActivityPub\UndoCommandHandler;
use Mitra\CommandBus\Handler\Command\CreateUserCommandHandler;
use Mitra\CommandBus\SymfonyMessengerCommandBus;
use Mitra\CommandBus\SymfonyMessengerEventBus;
use Mitra\CommandBus\SymfonyMessengerHandlersLocator;
use Mitra\Repository\SubscriptionRepository;
use Mitra\Slim\UriGenerator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory;
use Symfony\Component\Messenger\Transport\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class CommandBusServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerCommandHandlers($container);
        $this->registerEventHandlers($container);

        $container[TransportFactoryInterface::class] = static function (
            Container $container
        ): TransportFactoryInterface {
            $factories = [
                new DoctrineTransportFactory($container['doctrine.orm.manager_registry']),
                new InMemoryTransportFactory(),
            ];

            if (extension_loaded('amqp')) {
                $factories[] = new AmqpTransportFactory();
            }

            if (extension_loaded('redis')) {
                $factories[] = new RedisTransportFactory();
            }

            return new TransportFactory($factories);
        };

        if (null !== $container['queue_dns']) {
            $container[TransportInterface::class] = static function (Container $container): TransportInterface {
                return $container[TransportFactoryInterface::class]->createTransport(
                    $container['queue_dns'],
                    new PhpSerializer()
                );
            };
        }

        // Messenger middlewares
        $container[SendMessageMiddleware::class] = static function (Container $container): SendMessageMiddleware {
            $sendersLocator = new SendersLocator(
                $container['mappings']['bus']['routing'],
                $container[PsrContainerInterface::class]
            );
            return new SendMessageMiddleware($sendersLocator);
        };

        $container[DispatchAfterCurrentBusMiddleware::class] = static function (): DispatchAfterCurrentBusMiddleware {
            return new DispatchAfterCurrentBusMiddleware();
        };

        // Command buses
        $container[EventBusInterface::class] = static function (Container $container): EventBusInterface {
            $eventHandlersLocator = new SymfonyMessengerHandlersLocator(
                $container[PsrContainerInterface::class],
                $container['mappings']['bus']['event_handlers']
            );

            $eventBus = new MessageBus([
                $container[DispatchAfterCurrentBusMiddleware::class],
                new DoctrineTransactionMiddleware($container['doctrine.orm.manager_registry']),
                $container[SendMessageMiddleware::class],
                new HandleMessageMiddleware($eventHandlersLocator, true),
            ]);

            return new SymfonyMessengerEventBus($eventBus);
        };

        $container[CommandBusInterface::class] = static function (Container $container): CommandBusInterface {
            $commandHandlersLocator = new SymfonyMessengerHandlersLocator(
                $container[PsrContainerInterface::class],
                $container['mappings']['bus']['command_handlers']
            );

            $commandBus = new MessageBus([
                $container[DispatchAfterCurrentBusMiddleware::class],
                new DoctrineTransactionMiddleware($container['doctrine.orm.manager_registry']),
                $container[SendMessageMiddleware::class],
                new HandleMessageMiddleware($commandHandlersLocator, false),
            ]);

            return new SymfonyMessengerCommandBus($commandBus);
        };
    }

    private function registerCommandHandlers(Container $container): void
    {
        $container[CreateUserCommandHandler::class] = static function (Container $container): CreateUserCommandHandler {
            return new CreateUserCommandHandler($container['doctrine.orm.em']);
        };

        $container[AssignActorCommandHandler::class] = static function (
            Container $container
        ): AssignActorCommandHandler {
            return new AssignActorCommandHandler($container[UriGenerator::class]);
        };

        $container[SendObjectToRecipientsCommandHandler::class] = static function (
            Container $container
        ): SendObjectToRecipientsCommandHandler {
            return new SendObjectToRecipientsCommandHandler(
                $container[ActivityPubClientInterface::class],
                $container[ExternalUserResolver::class],
                $container[UriGenerator::class],
                $container[LoggerInterface::class]
            );
        };

        $container[FollowCommandHandler::class] = static function (Container $container): FollowCommandHandler {
            return new FollowCommandHandler(
                $container['doctrine.orm.em'],
                $container[ExternalUserResolver::class],
                $container[SubscriptionRepository::class]
            );
        };

        $container[UndoCommandHandler::class] = static function (Container $container): UndoCommandHandler {
            return new UndoCommandHandler(
                $container[ExternalUserResolver::class],
                $container['doctrine.orm.em'],
                $container[SubscriptionRepository::class]
            );
        };
    }

    private function registerEventHandlers(Container $container): void
    {
    }
}
