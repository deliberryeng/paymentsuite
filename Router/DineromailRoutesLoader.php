<?php

/**
 * DineromailBundle for Symfony2
 *
 * This Bundle is part of Symfony2 Payment Suite
 *
 * @author David Pujadas <dpujadas@gmail.com>
 * @package DineromailBundle
 *
 * David Pujadas 2013
 */

namespace Dpujadas\DineromailBundle\Router;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dineromail router
 */
class DineromailRoutesLoader implements LoaderInterface
{

    /**
     * @var string
     * 
     * Execution route name
     */
    const ROUTE_NAME = 'dineromail_execute';

    const ROUTE_PROCESS_NAME = 'dineromail_process';

    /**
     * @var string
     * 
     * Execution controller route
     */
    private $controllerRoute;

    /**
     *  @var string
     */
    private $controllerProcessRoute;


    /**
     * @var boolean
     * 
     * Route is loaded
     */
    private $loaded = false;


    /**
     * Construct method
     * 
     * @param string $controllerRoute Controller route
     */
    public function __construct($controllerRoute, $controllerProcessRoute)
    {
        $this->controllerRoute = $controllerRoute;
        $this->controllerProcessRoute = $controllerProcessRoute;
    }


    /**
     * Loads a resource.
     *
     * @param mixed  $resource The resource
     * @param string $type     The resource type
     * 
     * @return RouteCollection
     * 
     * @throws RuntimeException Loader is added twice
     */
    public function load($resource, $type = null)
    {
        if ($this->loaded) {

            throw new \RuntimeException('Do not add this loader twice');
        }

        $routes = new RouteCollection();

        $routes->add(self::ROUTE_NAME, new Route($this->controllerRoute, array(
                '_controller'   =>  'DineromailBundle:Dineromail:execute',
        )));

        $routes->add(self::ROUTE_PROCESS_NAME, new Route($this->controllerProcessRoute, array(
                '_controller'   =>  'DineromailBundle:Dineromail:process',
        )));



        $this->loaded = true;

        return $routes;
    }


    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return 'dineromail' === $type;
    }


    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolverInterface A LoaderResolverInterface instance
     */
    public function getResolver()
    {
    }


    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolverInterface $resolver A LoaderResolverInterface instance
     */
    public function setResolver(LoaderResolverInterface $resolver)
    {
    }
}