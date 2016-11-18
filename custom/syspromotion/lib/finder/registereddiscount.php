<?php  

/**
* 
*/
class syspromotion_finder_registereddiscount
{
	
	public $column_edit = '编辑';
	public $column_edit_order = 1;
	public $column_edit_width = 10;

	public function column_edit(&$colList,$list){
        $nowTime = time();
        foreach($list as $k=>$row)
        {
            $url = url::route('shopadmin', ['app'=>'syspromotion','act'=>'edit','ctl'=>'admin_registereddiscount','finder_id'=>$_GET['_finder']['finder_id'],'regdis_id'=>$row['regdis_id']]);
            $title = '编辑';
            $target = '_blank';
            $colList[$k] = '<a href="' . $url . '" target="' . $target . '">' . $title . '</a>';
        }
	}
}

?>