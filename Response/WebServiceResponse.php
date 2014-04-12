<?php

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
