<?php
/**
 * 清除历史足迹
 */

class sysuser_tasks_cleanhistory extends base_task_abstract implements base_interface_task{

    public function exec($params=null)
    {
    	//足迹历史记录时长30天,自动清除历史足迹
    	$filter = array();
        $filter['create_time|lthan'] = strtotime('-30 days');
    	$ojbHistory = app::get('sysuser')->model('user_history');

        return $ojbHistory->delete($filter);
    }
}