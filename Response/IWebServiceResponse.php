<?php
/**
 * @author  Ethan Hann <ethanhann@gmail.com>
 * @license For the full copyright and license information, please view the LICENSE
 *          file that was distributed with this source code.
 */

namespace Ehann\Bundle\WebServiceBundle\Response;

interface IWebServiceResponse
{
    public function setContent($content);
    public function getContent();
}
