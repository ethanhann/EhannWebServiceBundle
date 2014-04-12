<?php

namespace Ehann\Bundle\WebServiceBundle\Response;

interface IWebServiceResponse
{
    public function setContent($content);
    public function getContent();
}
