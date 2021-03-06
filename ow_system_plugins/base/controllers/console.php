<?php

class BASE_CTRL_Console extends OW_ActionController
{
    public function listRsp()
    {
        $request = json_decode($_POST['request'], true);

        if ( !OW::getUser()->isAuthenticated() )
        {
            echo json_encode(['items' => []]);

            exit;
        }

        $event = new BASE_CLASS_ConsoleListEvent('console.load_list', $request, $request['data']);
        OW::getEventManager()->trigger($event);

        $responce = [];
        $responce['items'] = $event->getList();

        $responce['data'] = $event->getData();
        $responce['markup'] = [];

        /* @var $document OW_AjaxDocument */
        $document = OW::getDocument();

        $responce['markup']['scriptFiles'] = $document->getScripts();
        $responce['markup']['onloadScript'] = $document->getOnloadScript();
        $responce['markup']['styleDeclarations'] = $document->getStyleDeclarations();
        $responce['markup']['styleSheets'] = $document->getStyleSheets();
        $responce['markup']['beforeIncludes'] = $document->getScriptBeforeIncludes();

        echo json_encode($responce);

        exit;
    }
}