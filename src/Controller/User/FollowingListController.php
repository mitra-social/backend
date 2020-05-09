<?php

declare(strict_types=1);

namespace Mitra\Controller\User;

use Mitra\Dto\Response\ActivityPub\Actor\OrganizationDto;
use Mitra\Dto\Response\ActivityPub\Actor\PersonDto;
use Mitra\Dto\Response\ActivityStreams\LinkDto;
use Mitra\Dto\Response\ActivityStreams\ObjectDto;
use Mitra\Entity\Actor\Actor;
use Mitra\Entity\Actor\Organization;
use Mitra\Entity\Actor\Person;
use Mitra\Entity\Subscription;
use Mitra\Entity\User\ExternalUser;
use Mitra\Entity\User\InternalUser;
use Mitra\Http\Message\ResponseFactoryInterface;
use Mitra\Repository\InternalUserRepository;
use Mitra\Repository\SubscriptionRepository;
use Mitra\Serialization\Encode\EncoderInterface;
use Mitra\Slim\UriGenerator;

final class FollowingListController extends AbstractCollectionController
{
    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

    /**
     * @var UriGenerator
     */
    private $uriGenerator;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        InternalUserRepository $internalUserRepository,
        UriGenerator $uriGenerator,
        ResponseFactoryInterface $responseFactory
    ) {
        parent::__construct($internalUserRepository, $uriGenerator, $responseFactory);

        $this->subscriptionRepository = $subscriptionRepository;
        $this->uriGenerator = $uriGenerator;
    }

    /**
     * @param Actor $actorDto
     * @param int|null $page
     * @return array<ObjectDto|LinkDto>
     * @throws \Exception
     */
    protected function getItems(Actor $actorDto, ?int $page): array
    {
        $offset = null;
        $limit = null;

        if (null !== $page) {
            $offset = $page * self::ITEMS_PER_PAGE_LIMIT;
            $limit = self::ITEMS_PER_PAGE_LIMIT;
        }

        $items = $this->subscriptionRepository->findFollowingActorsForActor(
            $actorDto,
            $offset,
            $limit
        );

        $dtoItems = [];

        foreach ($items as $item) {
            /** @var Subscription $item */
            $subscribedActor = $item->getSubscribedActor();

            if ($subscribedActor instanceof Person) {
                $actorDto = new PersonDto();
            } elseif ($subscribedActor instanceof Organization) {
                $actorDto = new OrganizationDto();
            } else {
                throw new \RuntimeException(sprintf('Unsupported actor class `%s`', get_class($subscribedActor)));
            }

            $subscribedActorUser = $subscribedActor->getUser();

            if ($subscribedActorUser instanceof ExternalUser) {
                $actorDto->id = $subscribedActorUser->getExternalId();
                $actorDto->preferredUsername = $subscribedActorUser->getPreferredUsername();
                $actorDto->inbox = $subscribedActorUser->getInbox();
                $actorDto->outbox = $subscribedActorUser->getOutbox();
            } elseif ($subscribedActorUser instanceof InternalUser) {
                $actorDto->id = $this->uriGenerator->fullUrlFor('user-read', [
                    'username' => $subscribedActorUser->getUsername()
                ]);
                $actorDto->preferredUsername = $subscribedActorUser->getUsername();
                $actorDto->inbox = $this->uriGenerator->fullUrlFor('user-inbox-read', [
                    'username' => $subscribedActorUser->getUsername()
                ]);
                $actorDto->outbox = $this->uriGenerator->fullUrlFor('user-outbox-read', [
                    'username' => $subscribedActorUser->getUsername()
                ]);
            }

            $actorDto->name = $subscribedActor->getName();

            $dtoItems[] = $actorDto;
        }

        return $dtoItems;
    }

    protected function getTotalItemCount(Actor $requestedActor): int
    {
        return $this->subscriptionRepository->getFollowingCountForActor($requestedActor);
    }

    protected function getCollectionRouteName(): string
    {
        return 'user-following';
    }
}
