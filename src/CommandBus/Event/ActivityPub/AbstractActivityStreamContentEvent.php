<?php

declare(strict_types=1);

namespace Mitra\CommandBus\Event\ActivityPub;

use Mitra\CommandBus\EventInterface;
use Mitra\Dto\Response\ActivityStreams\ObjectDto;
use Mitra\Entity\ActivityStreamContent;
use Mitra\Entity\Actor\Actor;

abstract class AbstractActivityStreamContentEvent implements EventInterface
{
    /**
     * @var ActivityStreamContent
     */
    private $activityStreamContentEntity;

    /**
     * @var ObjectDto
     */
    private $activityStreamDto;

    /**
     * @var null|Actor
     */
    private $actor;

    /**
     * @var bool
     */
    private $resolveLinkedObjects;

    public function __construct(
        ActivityStreamContent $activityStreamContentEntity,
        ObjectDto $activityStreamDto,
        ?Actor $actor,
        bool $resolveLinkedObjects
    ) {
        $this->activityStreamContentEntity = $activityStreamContentEntity;
        $this->activityStreamDto = $activityStreamDto;
        $this->actor = $actor;
        $this->resolveLinkedObjects = $resolveLinkedObjects;
    }

    public function getActivityStreamContentEntity(): ActivityStreamContent
    {
        return $this->activityStreamContentEntity;
    }

    public function getActivityStreamDto(): ObjectDto
    {
        return $this->activityStreamDto;
    }

    /**
     * @return Actor|null
     */
    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function shouldDereferenceObjects(): bool
    {
        return $this->resolveLinkedObjects;
    }
}
