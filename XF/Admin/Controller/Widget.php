<?php

namespace DC\WidgetsExport\XF\Admin\Controller;

use XF\Mvc\ParameterBag;

class Widget extends XFCP_Widget
{
    public function actionExport()
    {
        $widgets = $this->getAllWidgets();

        if (!$widgets->count())
        {
            return $this->error(\XF::phrase('no_available_widgets_to_export'));
        }
        
        if ($this->isPost())
        {
            $widgetIds = $this->filter('widget_ids', 'array');

            if (empty($widgetIds))
            {
                return $this->error(\XF::phrase('no_available_widgets_to_export'));
            }

            $this->setResponseType('xml');

            foreach($widgets AS $widgetId => $widget)
            {
                if (!in_array($widgetId, $widgetIds))
                {
                    unset($widgets[$widgetId]);
                }
            }

            /** @var \DC\WidgetsExport\Service\Widget\Export $widgetsExporter */
            $widgetsExporter = $this->service('DC\WidgetsExport:Widget\Export', $widgets);

            $viewParams = [
                'widgets' => $widgets,
                'xml' => $widgetsExporter->exportToXml(),
                'filename' => $widgetsExporter->getExportFileName()
            ];

            return $this->view('DC\WidgetsExport\XF\Admin\View\Widget\Export', '', $viewParams);
        }
        else
        {
            $viewParams = [
                'widgets' => $widgets
            ];

            return $this->view('XF:Widget\Export', 'dcWidgetsExport_widgets_export', $viewParams);
        }
    }

    public function actionImport()
    {
        if ($this->isPost())
        {
            $upload = $this->request->getFile('upload', false);

            if (!$upload)
			{
				return $this->error(\XF::phrase('please_upload_valid_style_xml_file'));
			}

            /** @var \DC\WidgetsExport\Service\Widget\Import $widgetsImporter */
            $widgetsImporter = $this->service('DC\WidgetsExport:Widget\Import');

            $xmlFile = null;

			switch ($upload->getExtension())
			{
				case 'xml':
					$xmlFile = $upload->getTempFile();
					break;

				default:
					return $this->error(\XF::phrase('please_upload_valid_style_xml_file'));
			}

            try
			{
				$document = \XF\Util\Xml::openFile($xmlFile);
			}
			catch (\Exception $e)
			{
				$document = null;
			}

            if (!$widgetsImporter->isValidXml($document, $error))
            {
                return $this->error($error);
            }

            $import = $widgetsImporter->importFromXml($document);

            if (is_string($import))
            {
                return $this->error($import);
            }

            return $this->redirect($this->buildLink('widgets'));
        }
        else
        {
            return $this->view('XF:Widget\Import', 'dcWidgetsExport_widgets_import');
        }
    }

    public function actionDeleteAll()
    {
        if ($this->isPost())
        {
            $db = $this->app()->db();
		    $db->beginTransaction();
            
            $widgets = $this->getAllWidgets();

            foreach($widgets AS $widget)
            {
                $widget->delete(true, false);
            }

            $db->commit();
            
            return $this->redirect($this->buildLink('widgets'));
        }
        else
        {
            $viewParams = [
                'confirmUrl' => $this->buildLink('widgets/delete-all'),
                'contentTitle' => \XF::phrase('all_widgets')
            ];

            return $this->view('XF:Delete\Delete', 'public:delete_confirm', $viewParams);
        }
    }

    /**
     * @return \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Widget>
     */
    protected function getAllWidgets()
    {
        return $this->finder('XF:Widget')
            ->order('widget_id', 'DESC')
            ->fetch();
    }
}