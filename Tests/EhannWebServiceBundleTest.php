<?php
/**
 * Created by PhpStorm.
 * User: ethan
 * Date: 4/12/14
 * Time: 1:07 PM
 */

namespace Ehann\Bundle\WebServiceBundle\Tests;


use Ehann\Bundle\WebServiceBundle\EhannWebServiceBundle;

/**
 * Class EhannWebServiceBundleTest
 *
 * @package  Ehann\Bundle\WebServiceBundle\Tests
 */
class EhannWebServiceBundleTest extends \PHPUnit_Framework_TestCase
{
    public function testBundle()
    {
        $bundle = new EhannWebServiceBundle();

        $this->assertInstanceOf('Symfony\Component\HttpKernel\Bundle\Bundle', $bundle);
    }
}
