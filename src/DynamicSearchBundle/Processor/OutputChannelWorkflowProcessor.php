<?php

namespace DynamicSearchBundle\Processor;

use DynamicSearchBundle\Configuration\ConfigurationInterface;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Factory\PaginatorFactoryInterface;
use DynamicSearchBundle\Manager\IndexManagerInterface;
use DynamicSearchBundle\Manager\OutputChannelManagerInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelResult;
use DynamicSearchBundle\OutputChannel\OutputChannelResultInterface;
use DynamicSearchBundle\OutputChannel\RuntimeOptions\RuntimeOptionsProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutputChannelWorkflowProcessor implements OutputChannelWorkflowProcessorInterface
{
    /**
     * @var ConfigurationInterface
     */
    protected $configuration;

    /**
     * @var OutputChannelManagerInterface
     */
    protected $outputChannelManager;

    /**
     * @var IndexManagerInterface
     */
    protected $indexManager;

    /**
     * @var PaginatorFactoryInterface
     */
    protected $paginatorFactory;

    /**
     * @param ConfigurationInterface        $configuration
     * @param OutputChannelManagerInterface $outputChannelManager
     * @param IndexManagerInterface         $indexManager
     * @param PaginatorFactoryInterface     $paginatorFactory
     */
    public function __construct(
        ConfigurationInterface $configuration,
        OutputChannelManagerInterface $outputChannelManager,
        IndexManagerInterface $indexManager,
        PaginatorFactoryInterface $paginatorFactory
    ) {
        $this->configuration = $configuration;
        $this->outputChannelManager = $outputChannelManager;
        $this->indexManager = $indexManager;
        $this->paginatorFactory = $paginatorFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatchOutputChannelQuery(string $contextName, string $outputChannelName, array $contextOptions): OutputChannelResultInterface
    {
        $contextDefinition = $this->configuration->getContextDefinition(ContextDataInterface::CONTEXT_DISPATCH_TYPE_FETCH, $contextName);
        if (!$contextDefinition instanceof ContextDataInterface) {
            // throw
        }

        $indexManager = $this->indexManager->getIndexProvider($contextDefinition);
        if (!$indexManager instanceof IndexManagerInterface) {
            // throw
        }

        $outputChannelService = $this->outputChannelManager->getOutputChannel($contextDefinition, $outputChannelName);
        if (!$outputChannelService instanceof OutputChannelInterface) {
            // throw
        }

        $outputChannelRuntimeOptionsProviderName = $contextDefinition->getOutputChannelRuntimeOptionsProvider($outputChannelName);
        $outputChannelRuntimeOptionsProvider = $this->outputChannelManager->getOutputChannelRuntimeOptionsProvider($outputChannelRuntimeOptionsProviderName);
        if (!$outputChannelRuntimeOptionsProvider instanceof RuntimeOptionsProviderInterface) {
            // throw
        }

        $indexProviderOptions = $contextDefinition->getIndexProviderOptions($indexManager);
        $outputChannelServiceName = $contextDefinition->getOutputChannelServiceName($outputChannelName);

        $eventDispatcher = new OutputChannelModifierEventDispatcher($outputChannelServiceName, $outputChannelName, $contextDefinition, $this->outputChannelManager);

        $eventData = $eventDispatcher->dispatchAction('resolve_options', ['optionsResolver' => new OptionsResolver()]);
        $outputChannelOptions = $contextDefinition->getOutputChannelOptions($outputChannelName, $outputChannelService, $eventData->getParameter('optionsResolver'));

        $outputChannelRuntimeOptionsProvider->setDefaultOptions($outputChannelOptions);

        $outputChannelService->setEventDispatcher($eventDispatcher);
        $outputChannelService->setRuntimeParameterProvider($outputChannelRuntimeOptionsProvider);

        $contextOptions['event_dispatcher'] = $eventDispatcher;
        $result = $outputChannelService->execute($indexProviderOptions, $outputChannelOptions, $contextOptions);

        $documentFields = $this->parseDocumentFieldsConfig($contextDefinition);

        if ($outputChannelService->needsPaginator()) {
            $paginator = $this->paginatorFactory->create($outputChannelService->getPaginatorAdapterClass(), $result);
            $paginator->setItemCountPerPage($outputChannelRuntimeOptionsProvider->getMaxPerPage());
            $paginator->setCurrentPageNumber($outputChannelRuntimeOptionsProvider->getCurrentPage());
            $result = $paginator;
        }

        return new OutputChannelResult($contextName, $outputChannelServiceName, $outputChannelName, $outputChannelRuntimeOptionsProvider, $result, $documentFields);
    }

    /**
     * @param ContextDataInterface $contextDefinition
     *
     * @return array
     */
    protected function parseDocumentFieldsConfig(ContextDataInterface $contextDefinition)
    {
        $documentFields = $contextDefinition->getDocumentFieldsConfig();

        $parsedDocumentFields = [];
        foreach ($documentFields as $documentFieldName => $documentField) {
            $parsedDocumentFields[$documentFieldName] = $documentField['output_channel'];
        }

        return $parsedDocumentFields;
    }
}