<?php
/**
 * @author  Ethan Hann <ethanhann@gmail.com>
 * @license For the full copyright and license information, please view the LICENSE
 *          file that was distributed with this source code.
 */

namespace Ehann\Bundle\WebServiceBundle\Response;

class WebServiceResponse implements IWebServiceResponse
{
    protected $content;

    public function __construct($content = '')
    {
        $this->content = $content;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }
}
