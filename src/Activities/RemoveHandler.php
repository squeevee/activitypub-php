<?php

namespace ActivityPub\Activities;

use ActivityPub\Objects\CollectionsService;
use ActivityPub\Objects\ObjectsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RemoveHandler implements EventSubscriberInterface
{
    /**
     * @var ObjectsService
     */
    private $objectsService;

    /**
     * @var CollectionsService
     */
    private $collectionsService;

    public static function getSubscribedEvents()
    {
        return array(
            InboxActivityEvent::NAME => 'handleRemove',
            OutboxActivityEvent::NAME => 'handleRemove',
        );
    }

    public function __construct( ObjectsService $objectsService,
                                 CollectionsService $collectionsService )
    {
        $this->objectsService = $objectsService;
        $this->collectionsService = $collectionsService;
    }

    public function handleRemove( ActivityEvent $event )
    {
        $activity = $event->getActivity();
        if ( $activity['type'] !== 'Remove' ) {
            return;
        }
        $collectionId = $activity['target'];
        if ( is_array( $collectionId ) && array_key_exists( 'id', $collectionId ) ) {
            $collectionId = $collectionId['id'];
        }
        $collection = $this->objectsService->dereference( $collectionId );
        $requestActor = $event->getRequest()->attributes->get( 'actor' );
        $requestActorHost = parse_url( $requestActor['id'], PHP_URL_HOST );
        $collectionHost = parse_url( $collection['id'], PHP_URL_HOST );
        if ( $requestActorHost !== $collectionHost ) {
            throw new AccessDeniedHttpException();
        }
        $objectId = $activity['object'];
        if ( is_array( $objectId ) && array_key_exists( 'id', $objectId ) ) {
            $objectId = $objectId['id'];
        }
        if ( ! is_string( $objectId ) ) {
            return;
        }
        $this->collectionsService->removeItem( $collection, $objectId );
    }
}