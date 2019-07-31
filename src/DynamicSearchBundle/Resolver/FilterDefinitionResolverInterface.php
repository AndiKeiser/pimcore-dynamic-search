<?php

namespace DynamicSearchBundle\Resolver;

use DynamicSearchBundle\Exception\Resolver\DefinitionNotFoundException;
use DynamicSearchBundle\Filter\Definition\FilterDefinitionBuilderInterface;

interface FilterDefinitionResolverInterface
{
    /**
     * @param string      $contextName
     * @param string      $outputChannelName
     * @param string|null $subOutputChannelName
     *
     * @return FilterDefinitionBuilderInterface[]
     *
     * @throws DefinitionNotFoundException
     */
    public function resolve(string $contextName, string $outputChannelName, ?string $subOutputChannelName);
}