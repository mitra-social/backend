<?php

declare(strict_types=1);

namespace Mitra\Mapping\Dto\Response\ActivityPub;

use Mitra\Dto\Response\ActivityPub\Actor\PersonDto;
use Mitra\Dto\Response\UserResponseDto;
use Mitra\Entity\Actor\Person;
use Mitra\Entity\User\ExternalUser;
use Mitra\Entity\User\InternalUser;
use Mitra\Mapping\Dto\EntityToDtoMappingInterface;
use Mitra\Mapping\Dto\InvalidEntityException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorInterface;

final class PersonDtoMapping implements EntityToDtoMappingInterface
{
    /**
     * @var RouteCollectorInterface
     */
    private $routeCollector;

    public function __construct(RouteCollectorInterface $routeCollector)
    {
        $this->routeCollector = $routeCollector;
    }

    public static function getDtoClass(): string
    {
        return PersonDto::class;
    }

    public static function getEntityClass(): string
    {
        return Person::class;
    }

    /**
     * @param object|InternalUser $entity
     * @param ServerRequestInterface $request
     * @return object|UserResponseDto
     * @throws InvalidEntityException
     */
    public function toDto(object $entity, ServerRequestInterface $request): object
    {
        if (!$entity instanceof Person) {
            throw InvalidEntityException::fromEntity($entity, static::getEntityClass());
        }

        $routeParser = $this->routeCollector->getRouteParser();
        $uri = $request->getUri();

        $personDto = new PersonDto();
        $user = $entity->getUser();

        if ($user instanceof InternalUser) {
            $preferredUsername = $user->getUsername();
            $personDto->id = $routeParser->fullUrlFor($uri, 'user-read', ['preferredUsername' => $preferredUsername]);
            $personDto->preferredUsername = $preferredUsername;
            $personDto->inbox = $routeParser->fullUrlFor($uri, 'user-inbox', [
                'preferredUsername' => $preferredUsername
            ]);
            $personDto->outbox = $routeParser->fullUrlFor($uri, 'user-inbox', [
                'preferredUsername' => $preferredUsername
            ]);
        } elseif ($user instanceof ExternalUser) {
            $personDto->id = $user->getExternalId();
            $personDto->preferredUsername = $user->getPreferredUsername();
            $personDto->inbox = $user->getInbox();
            $personDto->outbox = $user->getOutbox();
        } else {
            throw new \RuntimeException(sprintf(
                'User `%s` can not be mapped to `%s` dto',
                get_class($user),
                PersonDto::class
            ));
        }

        $personDto->icon = $entity->getIcon();
        $personDto->name = $entity->getName();

        return $personDto;
    }
}
