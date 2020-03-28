<?php

declare(strict_types=1);

namespace Mitra\ServiceProvider;

use Mitra\Dto\DataToDtoManager;
use Mitra\Dto\DataToDtoPopulator;
use Mitra\Dto\DtoToEntityMapper;
use Mitra\Dto\EntityToDtoMapper;
use Mitra\Dto\Request\CreateUserRequestDto;
use Mitra\Dto\Request\TokenRequestDto;
use Mitra\Dto\RequestToDtoManager;
use Mitra\Dto\Response\ActivityStreams\Activity\CreateDto;
use Mitra\Dto\Response\ActivityStreams\ArticleDto;
use Mitra\Dto\Response\ActivityStreams\AudioDto;
use Mitra\Dto\Response\ActivityStreams\DocumentDto;
use Mitra\Dto\Response\ActivityStreams\EventDto;
use Mitra\Dto\Response\ActivityStreams\ImageDto;
use Mitra\Dto\Response\ActivityStreams\NoteDto;
use Mitra\Dto\Response\ActivityStreams\VideoDto;
use Mitra\Mapping\Dto\Request\CreateUserRequestDtoMapping;
use Mitra\Mapping\Dto\Response\UserResponseDtoMapping;
use Mitra\Mapping\Dto\Response\ViolationListDtoMapping;
use Mitra\Mapping\Dto\Response\ViolationDtoMapping;
use Mitra\Serialization\Decode\DecoderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollector;

final class DtoServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $this->registerDataToDtoPopulators($container);
        $this->registerDtoToEntityMappings($container);
        $this->registerEntityToDtoMappings($container);

        $container[DtoToEntityMapper::class] = static function (Container $container): DtoToEntityMapper {
            return new DtoToEntityMapper($container[ContainerInterface::class], [
                CreateUserRequestDtoMapping::class,
            ]);
        };

        $container[EntityToDtoMapper::class] = static function (Container $container): EntityToDtoMapper {
            return new EntityToDtoMapper($container[ContainerInterface::class], [
                UserResponseDtoMapping::class,
                ViolationListDtoMapping::class,
                ViolationDtoMapping::class,
            ]);
        };

        $container[DataToDtoManager::class] = static function (Container $container): DataToDtoManager {
            return new DataToDtoManager($container[ContainerInterface::class], [
                CreateUserRequestDto::class => DataToDtoPopulator::class . CreateUserRequestDto::class,
                TokenRequestDto::class => DataToDtoPopulator::class . TokenRequestDto::class,

                ArticleDto::class => DataToDtoPopulator::class . ArticleDto::class,
                DocumentDto::class => DataToDtoPopulator::class . DocumentDto::class,
                AudioDto::class => DataToDtoPopulator::class . AudioDto::class,
                ImageDto::class => DataToDtoPopulator::class . ImageDto::class,
                VideoDto::class => DataToDtoPopulator::class . VideoDto::class,
                NoteDto::class => DataToDtoPopulator::class . NoteDto::class,
                EventDto::class => DataToDtoPopulator::class . EventDto::class,
                CreateDto::class => DataToDtoPopulator::class . CreateDto::class,
            ]);
        };

        $container[RequestToDtoManager::class] = static function (Container $container): RequestToDtoManager {
            return new RequestToDtoManager($container[DataToDtoManager::class], $container[DecoderInterface::class]);
        };
    }

    private function registerDataToDtoPopulators(Container $container): void
    {
        $container[DataToDtoPopulator::class . CreateUserRequestDto::class] = static function (): DataToDtoPopulator {
            return new DataToDtoPopulator(CreateUserRequestDto::class);
        };

        $container[DataToDtoPopulator::class . TokenRequestDto::class] = static function (): DataToDtoPopulator {
            return new DataToDtoPopulator(TokenRequestDto::class);
        };

        // ActivityStream
        $activityStreamDtoClasses = [
            ArticleDto::class,
            DocumentDto::class,
            AudioDto::class,
            ImageDto::class,
            VideoDto::class,
            NoteDto::class,
            EventDto::class,
            CreateDto::class,
        ];

        foreach ($activityStreamDtoClasses as $activityStreamDtoClass) {
            $container[DataToDtoPopulator::class . $activityStreamDtoClass] = static function (): DataToDtoPopulator {
                return new DataToDtoPopulator(ArticleDto::class);
            };
        }
    }

    private function registerDtoToEntityMappings(Container $container): void
    {
        $container[CreateUserRequestDtoMapping::class] = static function (): CreateUserRequestDtoMapping {
            return new CreateUserRequestDtoMapping();
        };
    }

    private function registerEntityToDtoMappings(Container $container): void
    {
        $container[ViolationDtoMapping::class] = static function (): ViolationDtoMapping {
            return new ViolationDtoMapping();
        };

        $container[ViolationListDtoMapping::class] = static function (Container $container): ViolationListDtoMapping {
            return new ViolationListDtoMapping($container[ViolationDtoMapping::class]);
        };

        $container[UserResponseDtoMapping::class] = static function (Container $container): UserResponseDtoMapping {
            return new UserResponseDtoMapping($container[RouteCollector::class]);
        };
    }
}
