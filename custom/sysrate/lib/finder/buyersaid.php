<?php
class sysrate_finder_buyersaid{

	public $column_edit = "编辑";
    public $column_edit_order = 1;
    public function column_edit(&$colList, $list)
    {
        foreach($list as $k=>$row)
        {
            $url = '?app=sysrate&ctl=buyersaid&act=create&finder_id='.$_GET['_finder']['finder_id'].'&p[0]='.$row['said_id'];
            $target = 'dialog::  {title:\''.app::get('sysrate')->_('编辑').'\', width:800, height:550}';
            $title = app::get('sysrate')->_('编辑');
            $button = '<a href="' . $url . '" target="' . $target . '">' . $title . '</a>';
            $colList[$k] = $button;
        }
    }
}