<?php
namespace ActivityPub\Controllers;

use ActivityPub\Activities\InboxActivityEvent;
use ActivityPub\Activities\OutboxActivityEvent;
use ActivityPub\Entities\ActivityPubObject;
use ActivityPub\Objects\ObjectsService;
use ActivityPub\Utils\Util;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * The PostController is responsible for handling incoming ActivityPub POST requests
 */
class PostController
{
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var ObjectsService
     */
    private $objectsService;

    public function __construct( EventDispatcher $eventDispatcher,
                                 ObjectsService $objectsService )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->objectsService = $objectsService;
    }

    /**
     * Handles an incoming POST request
     *
     * Either dispatches an inbox/outbox activity event or throws the appropriate
     * HTTP error.
     * @param Request $request The request
     */
    public function handle( Request $request )
    {
        $uri = Util::getUriWithoutQuery( $request->getUri() );
        $object = $this->objectsService->dereference( $uri, true );
        if ( ! $object ) {
            throw new NotFoundHttpException;
        }
        $inboxField = $object->getReferencingField( 'inbox' );
        if ( $inboxField ) {
            $activity = json_decode( $request->getContent(), true );
            if ( ! $activity || ! array_key_exists( 'actor', $activity ) ) {
                throw new BadRequestHttpException();
            }
            $activityActor = $this->getActivityActor( $activity );
            if ( ! $activityActor) {
                throw new BadRequestHttpException();
            }
            if ( ! $request->attributes->has( 'signed' ) ||
                 ! $this->authorized( $request, $activityActor ) ) {
                throw new UnauthorizedHttpException(
                    'Signature realm="ActivityPub",headers="(request-target) host date"'
                );
            }
            $actorWithInbox = $inboxField->getObject();
            $event = new InboxActivityEvent( $activity, $actorWithInbox, $request );
            $this->eventDispatcher->dispatch( InboxActivityEvent::NAME, $event );
            return $event->getResponse();
        }
        $outboxField = $object->getReferencingField( 'outbox' );
        if ( $outboxField ) {
            $actorWithOutbox = $outboxField->getObject();
            if ( ! $this->authorized( $request, $actorWithOutbox ) ) {
                throw new UnauthorizedHttpException(
                    'Signature realm="ActivityPub",headers="(request-target) host date"'
                );
            }
            $activity = json_decode( $request->getContent(), true );
            if ( ! $activity ) {
                throw new BadRequestHttpException();
            }
            $event = new OutboxActivityEvent( $activity, $actorWithOutbox, $request );
            $this->eventDispatcher->dispatch( OutboxActivityEvent::NAME, $event );
            return $event->getResponse();
        } 
        throw new MethodNotAllowedHttpException( array( Request::METHOD_GET ) );
    }

    private function getActivityActor( array $activity )
    {
        $actor = $activity['actor'];
        if ( is_array( $actor ) && array_key_exists( 'id', $actor ) ) {
            return $this->objectsService->dereference( $actor['id'] );
        } else if ( is_string( $actor ) ) {
            return $this->objectsService->dereference( $actor );
        }
    }

    private function authorized( Request $request, ActivityPubObject $activityActor )
    {
        if ( ! $request->attributes->has( 'actor' ) ) {
            return false;
        }
        $requestActor = $request->attributes->get( 'actor' );
        if ( $requestActor['id'] !== $activityActor['id'] ) {
            return false;
        }
        return true;
    }

    private function objectWithField( string $name, string $value )
    {
        $results = $this->objectsService->query( array( $name => $value ) );
        if ( count( $results ) === 0 ) {
            return false;
        }
        return $results[0];
    }
}
?>
