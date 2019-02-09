<?php
namespace ActivityPub\WebFinger;

use ActivityPub\Objects\ObjectsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\EventDispatcher\Tests\CallableClass;

class WebFingerBuilder
{
    private $objectsService;
    private $canonizeFunction;

    public function __construct( ObjectsService $objectsService, callable $canonizeFunction )
    {
        if ( !is_callable($canonizeFunction) ) {
            throw new Exception('A valid canonize function must be set in ActivityPubConfig in order to use WebFingerBuilder');
        }

        $this->objectsService = $objectsService;
        $this->canonizeFunction = $canonizeFunction;
    }

    //Does not support the 'rel' parameter
    //If the resource could not be found, this returns null,
    //which should correspond to a 404 error.
    public function build( Request $request )
    {
        

        $resourceString = $request->query->get('resource');

        if ( preg_match('^acct:[A-Za-z0-9\-\._~!\$\&\'\(\)\*\+,;=]+@[A-Za-z0-9\.\-]+$', $resourceString) !== 1 ) {
            //unsupported or malformed
            return null;
        }

        $canon = $this->canonizeFunction(explode($resourceString, ':')[1]);
        $object = $this->objectsService->dereference( $canon, true );

        if ( !$object ) {
            //object not found
            return null;
        }

        return new JsonResponse(array(
            'subject' => $resourceString,
            'link' => array(
                'rel' => 'self',
                'href' => $canon
            )
        ));
    }
}