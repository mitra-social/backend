<?php

namespace Mitra\Tests\Integration;

use Firebase\JWT\JWT;
use HttpSignatures\Algorithm;
use HttpSignatures\HeaderList;
use HttpSignatures\Key;
use HttpSignatures\Signer;
use Mitra\CommandBus\Command\CreateUserCommand;
use Mitra\CommandBus\CommandBusInterface;
use Mitra\Entity\Actor\Person;
use Mitra\Entity\User\InternalUser;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;

/**
 * @method ContainerInterface getContainer()
 */
trait CreateUserTrait
{
    protected function createUser(?string $password = null): InternalUser
    {
        $userId = Uuid::uuid4()->toString();
        $username = 'john.doe.' . uniqid();
        $plaintextPassword = $password ?? 's0mePässw0rd';

        $user = new InternalUser($userId, $username, $username . '@example.com');
        $user->setPlaintextPassword($plaintextPassword);

        $actor = new Person($user);

        $user->setActor($actor);

        $this->getContainer()->get(CommandBusInterface::class)->handle(new CreateUserCommand($user));

        return $user;
    }

    protected function createTokenForUser(InternalUser $user): string
    {
        return JWT::encode(['userId' => $user->getId()], $this->getContainer()->get('jwt.secret'));
    }

    protected function signRequest(InternalUser $user, RequestInterface $request): RequestInterface
    {
        return (new Signer(
            new Key(sprintf('http://test.localhost/user/%s#main-key', $user->getUsername()), $user->getPrivateKey()),
            Algorithm::create('rsa-sha256'),
            new HeaderList(['(request-target)', 'Host', 'Accept'])
        ))->sign($request);
    }
}
