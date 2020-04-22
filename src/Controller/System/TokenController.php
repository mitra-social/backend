<?php

declare(strict_types=1);

namespace Mitra\Controller\System;

use Mitra\Authentication\TokenIssueException;
use Mitra\Authentication\TokenProvider;
use Mitra\Dto\Request\TokenRequestDto;
use Mitra\Dto\RequestToDtoTransformer;
use Mitra\Dto\Response\TokenResponseDto;
use Mitra\Http\Message\ResponseFactoryInterface;
use Mitra\Serialization\Encode\EncoderInterface;
use Mitra\Validator\ValidatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TokenController
{

    /**
     * @var TokenProvider
     */
    private $tokenProvider;

    /**
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var RequestToDtoTransformer
     */
    private $requestToDtoManager;

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param EncoderInterface $encoder
     * @param ValidatorInterface $validator
     * @param TokenProvider $tokenProvider
     * @param RequestToDtoTransformer $dataToDtoManager
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        EncoderInterface $encoder,
        ValidatorInterface $validator,
        TokenProvider $tokenProvider,
        RequestToDtoTransformer $dataToDtoManager
    ) {
        $this->responseFactory = $responseFactory;
        $this->encoder = $encoder;
        $this->validator = $validator;
        $this->tokenProvider = $tokenProvider;
        $this->requestToDtoManager = $dataToDtoManager;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $accept = $request->getAttribute('accept');

        /** @var TokenRequestDto $tokenRequestDto */
        $tokenRequestDto = $this->requestToDtoManager->fromRequest($request, TokenRequestDto::class);

        if (($violationList = $this->validator->validate($tokenRequestDto))->hasViolations()) {
            return $this->responseFactory->createResponseFromViolationList($violationList, $request, $accept);
        }

        try {
            $token = $this->tokenProvider->generate($tokenRequestDto->username, $tokenRequestDto->password);

            $tokenResponseDto = new TokenResponseDto();
            $tokenResponseDto->token = $token;

            $response = $this->responseFactory->createResponse(201);

            $response->getBody()->write($this->encoder->encode($tokenResponseDto, $accept));
        } catch (TokenIssueException $e) {
            $response = $this->responseFactory->createResponse(401);
        }

        return $response;
    }
}
