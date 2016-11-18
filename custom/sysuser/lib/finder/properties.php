<?php
class sysuser_finder_properties{

    public $column_edit = "编辑";
    public $column_edit_order = 1;
    public function column_edit(&$colList, $list)
    {
        foreach($list as $k=>$row)
        {
            $url = '?app=sysuser&ctl=admin_properties&act=create&finder_id='.$_GET['_finder']['finder_id'].'&p[0]='.$row['properties_id'];
            $target = 'dialog::  {title:\''.app::get('sysuser')->_('属性编辑').'\', width:500, height:400}';
            $title = app::get('sysuser')->_('编辑');
            $button = '<a href="' . $url . '" target="' . $target . '">' . $title . '</a>';
            $colList[$k] = $button;
        }
    }
}
