<?php

declare(strict_types=1);

namespace Mitra\Controller\User;

use Mitra\Dto\Response\ActivityPub\Actor\OrganizationDto;
use Mitra\Dto\Response\ActivityPub\Actor\PersonDto;
use Mitra\Dto\Response\ActivityStreams\CollectionDto;
use Mitra\Dto\Response\ActivityStreams\CollectionPageDto;
use Mitra\Dto\Response\ActivityStreams\CollectionPageInterface;
use Mitra\Dto\Response\ActivityStreams\LinkDto;
use Mitra\Dto\Response\ActivityStreams\ObjectDto;
use Mitra\Dto\Response\ActivityStreams\OrderedCollectionPageDto;
use Mitra\Dto\Response\ActivityStreams\TypeInterface;
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractCollectionController
{
    protected const ITEMS_PER_PAGE_LIMIT = 25;

    /**
     * @var InternalUserRepository
     */
    private $internalUserRepository;

    /**
     * @var UriGenerator
     */
    private $uriGenerator;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var callable
     */
    private $accessChecker;

    public function __construct(
        InternalUserRepository $internalUserRepository,
        UriGenerator $uriGenerator,
        ResponseFactoryInterface $responseFactory,
        EncoderInterface $encoder
    ) {
        $this->internalUserRepository = $internalUserRepository;
        $this->uriGenerator = $uriGenerator;
        $this->responseFactory = $responseFactory;
        $this->encoder = $encoder;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $accept = $request->getAttribute('accept');
        $username = $request->getAttribute('username');
        $pageNo = $request->getQueryParams()['page'] ?? null;

        $authenticatedUser = $this->internalUserRepository->resolveFromRequest($request);

        if (null === $requestedUser = $this->internalUserRepository->findByUsername($username)) {
            return $this->responseFactory->createResponse(404);
        }

        if (null !== $authenticatedUser && $authenticatedUser->getId() !== $requestedUser->getId()) {
            return $this->responseFactory->createResponse(401);
        }

        $requestedUsername = $requestedUser->getUsername();
        $requestedActor = $requestedUser->getActor();
        $collectionRouteName = $this->getCollectionRouteName();

        $totalItems = $this->getTotalItemCount($requestedActor);
        $totalPages = (int) ceil($totalItems / self::ITEMS_PER_PAGE_LIMIT);
        $lastPageNo = 0 === $totalPages ? 0 : $totalPages - 1;

        if (null === $pageNo) {
            $collectionDto = $this->getCollectionDto();
            $collectionDto->first = $this->uriGenerator->fullUrlFor(
                $collectionRouteName,
                ['username' => $requestedUsername],
                ['page' => 0]
            );
            $collectionDto->last = $this->uriGenerator->fullUrlFor(
                $collectionRouteName,
                ['username' => $requestedUsername],
                ['page' => $lastPageNo]
            );
        } else {
            $pageNo = (int) $pageNo;

            if ($pageNo > $lastPageNo) {
                return $this->responseFactory->createResponse(404);
            }

            $collectionDto = $this->getCollectionPageDto();
            $collectionDto->partOf = $this->uriGenerator->fullUrlFor(
                $collectionRouteName,
                ['username' => $requestedUsername]
            );

            if ($pageNo > 0) {
                $collectionDto->prev = $this->uriGenerator->fullUrlFor(
                    $collectionRouteName,
                    ['username' => $requestedUsername],
                    ['page' => $pageNo - 1]
                );
            }

            if ($pageNo < $lastPageNo) {
                $collectionDto->next = $this->uriGenerator->fullUrlFor(
                    $collectionRouteName,
                    ['username' => $requestedUsername],
                    ['page' => $pageNo + 1]
                );
            }

            if ($collectionDto instanceof OrderedCollectionPageDto) {
                $collectionDto->orderedItems = $this->getItems($requestedActor, $pageNo);
            } else {
                $collectionDto->items = $this->getItems($requestedActor, $pageNo);
            }
        }

        $collectionDto->context = TypeInterface::CONTEXT_ACTIVITY_STREAMS;
        $collectionDto->totalItems = $totalItems;

        $response = $this->responseFactory->createResponse();

        $response->getBody()->write($this->encoder->encode($collectionDto, $accept));

        return $response;
    }

    protected function getCollectionDto(): CollectionDto
    {
        return new CollectionDto();
    }

    protected function getCollectionPageDto(): CollectionPageInterface
    {
        return new CollectionPageDto();
    }

    /**
     * @param Actor $requestedActor
     * @param int|null $page
     * @return array<ObjectDto|LinkDto>
     */
    abstract protected function getItems(Actor $requestedActor, ?int $page): array;

    abstract protected function getTotalItemCount(Actor $requestedActor): int;

    abstract protected function getCollectionRouteName(): string;
}
