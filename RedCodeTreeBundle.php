<?php

namespace RedCode\TreeBundle;

use RedCode\TreeBundle\DependencyInjection\RedCodeTreeExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RedCodeTreeBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->registerExtension(new RedCodeTreeExtension());
    }
}
