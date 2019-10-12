<?php

class BASE_CMP_ModerationPanelList extends OW_Component
{
    public function __construct( array $items )
    {
        parent::__construct();
        
        $tplItems = [];
        
        foreach ( $items as $item )
        {
            $tplItems[] = array_merge([
                "label" => null,
                "url" => null,
                "count" => 0
            ], $item);
        }
        
        $this->assign("items", $tplItems);
    }
}