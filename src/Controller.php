<?php

namespace sPHPeed\Core;

use Twig_Environment as Twig;
use DI\Container;

abstract class Controller {

    /**
     * Undocumented variable
     *
     * @var DI\Container $container
     */
    protected $container;

    public function setContainer(Container $c) {
        $this->container = $c;
    }

    public function __get($name) {
        return $this->container->get($name);
    }

}