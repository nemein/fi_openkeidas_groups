<?php
class fi_openkeidas_groups_injector
{
    public function inject_process(midgardmvc_core_request $request)
    {
        static $connected = false;
        if ($connected)
        {
            return;
        }
        // Subscribe to content changed signals from Midgard
        midgard_object_class::connect_default('fi_openkeidas_groups_group', 'action-created', array('fi_openkeidas_groups_injector', 'handle_created'), array($request));
        $connected = true;
    }

    public static function handle_created(fi_openkeidas_groups_group $group, $params)
    {
        // Set current user as admin of the group
        $member = new fi_openkeidas_groups_group_member();
        $member->person = midgardmvc_core::get_instance()->authentication->get_person()->id;
        $member->grp = $group->id;
        $member->admin = true;
        $member->create();
        $member->approve();
    }
}
