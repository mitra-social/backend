<?php

declare(strict_types=1);

namespace Mitra\Controller\User;

use Mitra\Dto\Response\UserResponseDto;
use Mitra\Entity\User;
use Mitra\Http\Message\ResponseFactoryInterface;
use Mitra\Repository\UserRepository;
use Mitra\Serialization\Encode\EncoderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ReadUserController
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        EncoderInterface $encoder,
        UserRepository $userRepository
    ) {
        $this->responseFactory = $responseFactory;
        $this->encoder = $encoder;
        $this->userRepository = $userRepository;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $accept = $request->getAttribute('accept');
        $preferredUsername = $request->getAttribute('preferredUsername');

        if (null === $user = $this->userRepository->findOneByPreferredUsername($preferredUsername)) {
            return $this->responseFactory->createResponse(404);
        }

        return $this->responseFactory->createResponseFromEntity($user, UserResponseDto::class, $request, $accept);
    }
}
