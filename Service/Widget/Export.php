<?php

namespace DC\WidgetsExport\Service\Widget;

use XF\Service\AbstractService;
use XF\Util\Xml;
use XF\Entity\Widget;
use XF\Mvc\Entity\AbstractCollection;
use XF\Util\Json;

class Export extends AbstractService
{
    /**
     * @var AbstractCollection|Widget[]
     */
    protected $widgets;

    public function __construct(\XF\App $app, $widgets)
    {
        parent::__construct($app);
        $this->setWidgets($widgets);
    }

    public function setWidgets($widgets)
    {
        $this->widgets = $widgets;
    }

    public function getWidgets()
    {
        return $this->widgets;
    }

    public function exportToXml()
    {
        $document = $this->createXml();
        $widgetsParentNode = $this->getWidgetsParentNode($document);
        $document->appendChild($widgetsParentNode);

        return $document;
    }

    public function getExportFileName()
	{
		$timestamp = \XF::$time;
        
        return "widgets-{$timestamp}.xml";
	}

    /**
	 * @return \DOMDocument
	 */
	protected function createXml()
	{
		$document = new \DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		return $document;
	}

    protected function getWidgetsParentNode(\DOMDocument $document)
    {
        $widgetsParentNode = $document->createElement('widgets');

        foreach($this->widgets AS $widget)
        {
            $widgetsParentNode->appendChild($this->getWidgetNode($document, $widget));
        }

        return $widgetsParentNode;
    }

    protected function getWidgetNode(\DOMDocument $document, Widget $widget)
    {
        $widgetNode = $document->createElement('widget');
        $widgetNode->setAttribute('widget_id', $widget->widget_id);
        $widgetNode->setAttribute('widget_key', $widget->widget_key);
        $widgetNode->setAttribute('title', $widget->title);
        $widgetNode->setAttribute('definition_id', $widget->definition_id);

        $widgetNode->appendChild($this->getWidgetOptionsNode($document, $widget));
        $widgetNode->appendChild($this->getWidgetPositionsNode($document, $widget));
        $widgetNode->appendChild($this->getWidgetDisplayConditionNode($document, $widget));
        $widgetNode->appendChild($this->getWidgetConditionExpressionNode($document, $widget));

        return $widgetNode;
    }

    protected function getWidgetOptionsNode(\DOMDocument $document, Widget $widget)
    {
        $options = $widget->options;

        $optionsNode = $document->createElement('options');

        foreach($options AS $key => $value)
        {
            $option = $document->createElement('option');
            $option->setAttribute('key', $key);
            $option->appendChild(
                Xml::createDomElement($document, 'value', Json::jsonEncodePretty($value))
            );

            $optionsNode->appendChild($option);
        }

        return $optionsNode;
    }

    protected function getWidgetPositionsNode(\DOMDocument $document, Widget $widget)
    {
        $positions = $widget->positions;

        $positionsNode = $document->createElement('positions');

        foreach($positions AS $key => $value)
        {
            $position = $document->createElement('position');
            $position->setAttribute('key', $key);
            $position->appendChild(
                Xml::createDomElement($document, 'value', (int) $value)
            );

            $positionsNode->appendChild($position);
        }

        return $positionsNode;
    }

    protected function getWidgetDisplayConditionNode(\DOMDocument $document, Widget $widget)
    {
        $displayCondition = $widget->display_condition;

        $displayConditionNode = $document->createElement('display_condition');
        $displayConditionNode->appendChild(
            Xml::createDomElement($document, 'value_parameters', $displayCondition)
        );

        return $displayConditionNode;
    }

    protected function getWidgetConditionExpressionNode(\DOMDocument $document, Widget $widget)
    {
        $conditionExpression = $widget->condition_expression;

        $conditionExpressionNode = $document->createElement('condition_expression');
        $conditionExpressionNode->appendChild(
            Xml::createDomElement($document, 'value_parameters', $conditionExpression)
        );

        return $conditionExpressionNode;
    }
}