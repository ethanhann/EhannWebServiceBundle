<?php
/**
 * Created by PhpStorm.
 * User: ethan
 * Date: 4/12/14
 * Time: 12:07 PM
 */

namespace Ehann\Bundle\WebServiceBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MetadataControllerTest extends WebTestCase
{
    public function testShouldContainHeader()
    {
        $client = static::createClient();

        $client->request('GET', '/metadata');

        $this->assertContains('Metadata', $client->getResponse()->getContent());
    }
} 