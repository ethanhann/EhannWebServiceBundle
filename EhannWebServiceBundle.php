<?php

namespace Ehann\Bundle\WebServiceBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Sensio\Bundle\FrameworkExtraBundle\DependencyInjection\Compiler\AddParamConverterPass;

/**
 * EhannWebServiceBundle.
 *
 * @author  Ethan Hann <ethanhann@gmail.com>
 */
class EhannWebServiceBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddParamConverterPass());
    }
}
