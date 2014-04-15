<?php
/**
 * @author  Ethan Hann <ethanhann@gmail.com>
 * @license For the full copyright and license information, please view the LICENSE
 *          file that was distributed with this source code.
 */

namespace Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Request;

use Doctrine\Common\Collections\ArrayCollection;
use Ehann\Bundle\WebServiceBundle\Request\IWebServiceRequest;

class DocumentRequest implements IWebServiceRequest
{
    public $collection;

    /**
     * @param ArrayCollection $collection
     * @return $this
     */
    public function setCollection(ArrayCollection $collection)
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getCollection()
    {
        return $this->collection;
    }
}
