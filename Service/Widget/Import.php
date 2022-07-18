<?php

namespace DC\WidgetsExport\Service\Widget;

use XF\Service\AbstractService;
use XF\Util\Json;

class Import extends AbstractService
{
    public function isValidXml($rootElement, &$error = null)
	{
		if (!($rootElement instanceof \SimpleXMLElement))
		{
			$error = \XF::phrase('please_upload_valid_widgets_xml_file');
			return false;
		}

		if ($rootElement->getName() != 'widgets')
		{
			$error = \XF::phrase('please_upload_valid_widgets_xml_file');
			return false;
		}

		return true;
	}

    public function importFromXml(\SimpleXMLElement $document)
    {
        $db = $this->db();
		$db->beginTransaction();
        
        foreach($document->widget AS $widgetXml)
        {
            $widget = $this->createWidgetIfPossible($widgetXml);

            if (is_string($widget))
            {
                return $widget;
            }
        }

        $db->commit();

        $this->rebuildWidgetCache();
    }

    /**
     * @return void when successfully imported
     * @return string failed imported widget key
     */
    protected function createWidgetIfPossible(\SimpleXMLElement $widget)
    {
        $availableDefinitions = $this->getAvailableWidgetDefinitions();

        $key = (string) $widget['widget_key'];

        $definition = (string) $widget['definition_id'];

        if (!in_array($definition, $availableDefinitions))
        {
            return $key;
        }

        // If there's a widget with duplicated key
        // then we'll change the current key to 
        // widget_key_1, widget_key_2, ...
        $duplicateWidget = $this->em()->findOne('XF:Widget', ['widget_key' => $key]);
        while ($duplicateWidget)
        {
            $count = 1;
            $number_length = strlen($count);

            if ($count == 1)
            {
                $key .= "_{$count}"; // First time, just add a number at the end
            }
            else
            {
                $key = substr($key, 0, -$number_length); // Remove the number at the end
                $key .= $count; // Replace with another number

                // We don't need to add _ character since we haven't removed it
            }

            $duplicateWidget = $this->em()->findOne('XF:Widget', ['widget_key' => $key]);
            $count++;
        }

        $positions = $this->getWidgetPositions($widget);

        $options = $this->getWidgetOptions($widget);

        $displayCondition = (string) $widget->display_condition->value_parameters;

        $conditionExpression = (string) $widget->condition_expression->value_parameters;

        $title = (string) $widget['title'];

        $this->helperCreateWidget(
            $key, 
            $definition, 
            [
                'positions' => $positions,
                'options' => $options,
                'display_condition' => $displayCondition,
                'condition_expression' => $conditionExpression
            ],
            $title
        );
    }

    protected function getWidgetOptions(\SimpleXMLElement $widget)
    {
        $options = [];
        
        foreach($widget->options->option AS $option)
        {
            $key = (string) $option['key'];
            $value = Json::decodeJsonOrSerialized((string) $option->value);

            if ($key && $value)
            {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    protected function getWidgetPositions(\SimpleXMLElement $widget)
    {
        $positions = [];

        $availablePositions = $this->getAvailableWidgetPositions();

        foreach($widget->positions->position AS $position)
        {
            $key = (string) $position['key'];
            
            if (in_array($key, $availablePositions))
            {
                $value = (string) $position->value;

                $positions[$key] = $value;
            }
        }

        return $positions;
    }

    protected function getAvailableWidgetDefinitions()
    {
        return $this->finder('XF:WidgetDefinition')
            ->pluckFrom('definition_id')
            ->fetch()
            ->toArray();
    }

    protected function getAvailableWidgetPositions()
    {
        return $this->finder('XF:WidgetPosition')
            ->where('active', true)
            ->pluckFrom('position_id')
            ->fetch()
            ->toArray();
    }

    protected function helperCreateWidget($widgetKey, $definitionId, array $config, $title = '')
	{
		/** @var \XF\Entity\Widget $widget */
		$widget = $this->app->em()->create('XF:Widget');
		$widget->widget_key = $widgetKey;
		$widget->definition_id = $definitionId;
		$widget->bulkSet($config);
		$success = $widget->save(true, false);

		if ($success)
		{
			$masterTitle = $widget->getMasterPhrase();
			$masterTitle->phrase_text = $title;
			$masterTitle->save(true, false);
		}
	}

    protected function rebuildWidgetCache()
	{
		\XF::runOnce('widgetCacheRebuild', function()
		{
			$this->getWidgetRepo()->rebuildWidgetCache();
		});
	}

    /**
	 * @return \XF\Repository\Widget
	 */
	protected function getWidgetRepo()
	{
		return $this->repository('XF:Widget');
	}
}