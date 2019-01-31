<?php
namespace ActivityPub\Controllers;

use ActivityPub\Objects\ObjectsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WebFingerBuilder
{
    private $objectsService;

    public function __construct( ObjectsService $objectsService )
    {
        $this->objectsService = $objectsService;
    }

    //Does not support the 'rel' parameter
    //If the resource could not be found, this returns null,
    //which should correspond to a 404 error.
    public function build( Request $request, callable $canonize )
    {
        if ( !is_callable($canonize) ) {
            throw new Exception('Param "canonize" must be a callable');
        }

        $resourceString = $request->query->get('resource');

        if ( preg_match('^acct:[A-Za-z0-9\-\._~!\$\&\'\(\)\*\+,;=]+@[A-Za-z0-9\.\-]+$', $resourceString) !== 1 ) {
            //unsupported or malformed
            return null;
        }

        $canon = $canonize(explode($resourceString, ':')[1]);
        $object = $this->objectsService->dereference( $canon, true );

        if ( !$object ) {
            //object not found
            return null;
        }

        return new JsonRequest([
            'subject' => $resourceString,
            'link' => [
                'rel' => 'self',
                'href' => $canon
            ]
        ]);
    }
}