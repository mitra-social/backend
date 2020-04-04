<?php

declare(strict_types=1);

namespace Mitra\Controller\User;

use Mitra\CommandBus\Command\ActivityPub\FollowCommand;
use Mitra\CommandBus\Command\CreateUserCommand;
use Mitra\CommandBus\CommandBusInterface;
use Mitra\Dto\DataToDtoPopulatorInterface;
use Mitra\Dto\DataToDtoTransformer;
use Mitra\Dto\DtoToEntityMapper;
use Mitra\Dto\Request\CreateUserRequestDto;
use Mitra\Dto\RequestToDtoTransformer;
use Mitra\Dto\Response\ActivityStreams\Activity\CreateDto;
use Mitra\Dto\Response\ActivityStreams\Activity\FollowDto;
use Mitra\Dto\Response\ActivityStreams\LinkDto;
use Mitra\Dto\Response\ActivityStreams\ObjectDto;
use Mitra\Dto\Response\UserResponseDto;
use Mitra\Entity\Actor\Actor;
use Mitra\Entity\User\InternalUser;
use Mitra\Http\Message\ResponseFactoryInterface;
use Mitra\Mapping\Dto\ActivityStreamTypeToDtoClassMapping;
use Mitra\Repository\InternalUserRepository;
use Mitra\Serialization\Decode\DecoderInterface;
use Mitra\Serialization\Encode\EncoderInterface;
use Mitra\Validator\ValidatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OutboxController
{

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var DecoderInterface
     */
    private $decoder;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var DataToDtoPopulatorInterface
     */
    private $activityPubDataToDtoPopulator;

    /**
     * @var DtoToEntityMapper
     */
    private $dtoToEntityMapper;

    /**
     * @var InternalUserRepository
     */
    private $internalUserRepository;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        EncoderInterface $encoder,
        ValidatorInterface $validator,
        CommandBusInterface $commandBus,
        DataToDtoPopulatorInterface $activityPubDataToDtoPopulator,
        DecoderInterface $decoder,
        DtoToEntityMapper $dtoToEntityMapper,
        InternalUserRepository $internalUserRepository
    ) {
        $this->responseFactory = $responseFactory;
        $this->encoder = $encoder;
        $this->decoder = $decoder;
        $this->validator = $validator;
        $this->commandBus = $commandBus;
        $this->activityPubDataToDtoPopulator = $activityPubDataToDtoPopulator;
        $this->dtoToEntityMapper = $dtoToEntityMapper;
        $this->internalUserRepository = $internalUserRepository;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $accept = $request->getAttribute('accept');
        $username = $request->getAttribute('preferredUsername');
        $decodedRequestBody = $this->decoder->decode((string) $request->getBody(), $accept);

        if (null === $outboxUser = $this->internalUserRepository->findByUsername($username)) {
            return $this->responseFactory->createResponse(404);
        }

        if (!is_array($decodedRequestBody) || !array_key_exists('type', $decodedRequestBody)) {
            return $this->responseFactory->createResponse(400);
        }

        /** @var ObjectDto $objectDto */
        $objectDto = $this->activityPubDataToDtoPopulator->populate($decodedRequestBody);

        if (($violationList = $this->validator->validate($objectDto))->hasViolations()) {
            return $this->responseFactory->createResponseFromViolationList($violationList, $request, $accept);
        }

        /*$response = $this->responseFactory->createResponse();

        $response->getBody()->write(print_r($objectDto, true));

        return $response;*/

        $this->commandBus->handle($this->getCommandFromObject($outboxUser->getActor(), $objectDto));

        return $this->responseFactory->createResponseFromEntity($user, UserResponseDto::class, $request, $accept, 201);
    }

    private function getCommandFromObject(Actor $outboxActor, object $object): object
    {
        if ($object instanceof FollowDto) {
            return new FollowCommand($outboxActor, $object);
        }

        throw new \RuntimeException(sprintf('Type `%s` is currently not supported', $object->type));
    }
}
