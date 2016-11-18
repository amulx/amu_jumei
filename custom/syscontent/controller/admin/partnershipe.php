<?php
class syscontent_ctl_admin_partnershipe extends desktop_controller{

    public function index()
    {
        return $this->finder('syscontent_mdl_partnershipe',array(
            'title' => app::get('syscontent')->_('留言列表'),
        ));
    }
}
