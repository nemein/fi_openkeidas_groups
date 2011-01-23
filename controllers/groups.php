<?php
class fi_openkeidas_groups_controllers_groups
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_list(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $qb = new midgard_query_builder('fi_openkeidas_groups_group');
        $qb->set_limit(10);
        $qb->add_order('metadata.created', 'DESC');
        $this->data['groups'] = array();
        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $this->data['groups'][] = $this->prepare_for_list($group);
        }
    }

    public function get_active(array $args)
    {
        $qb = new midgard_query_builder('fi_openkeidas_groups_group');
        $qb->set_limit(5);
        $qb->add_order('metadata.score', 'DESC');
        $this->data['groups'] = array();
        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $this->data['groups'][] = $this->prepare_for_list($group);
        }
    }

    private function prepare_for_list(fi_openkeidas_groups_group $group)
    {
        $group->url = midgardmvc_core::get_instance()->dispatcher->generate_url('group_read', array('group' => $group->guid), $this->request);

        $member_qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $member_qb->add_constraint('grp', '=', $group->id);
        $group->members = $member_qb->count();

        return $group;
    }
}
