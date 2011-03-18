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
    
    public function get_search(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['form'] = midgardmvc_helper_forms::create('fi_openkeidas_groups_search');
        $this->data['form']->set_method('get');
        $search = $this->data['form']->add_field('search', 'text');
        $search_widget = $search->set_widget('text');
        $search_widget->set_label('Hae ryhmiä');
        
        $this->data['search_groups'] = array();
        if (   !isset($_GET['search'])
            || empty($_GET['search']))
        {
            return;
        }
        $search->set_value($_GET['search']);

        $qb = new midgard_query_builder('fi_openkeidas_groups_group');
        $qb->add_constraint('title', 'LIKE', "{$_GET['search']}%");
        $qb->add_order('metadata.score', 'DESC');
        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $this->data['search_groups'][] = $this->prepare_for_list($group);
        }
    }

    public function get_user(array $args)
    {
        $member_qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $member_qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $member_qb->add_constraint('metadata.isapproved', '=', true);
        $members = $member_qb->execute();

        $this->data['groups'] = array();
        if (empty($members))
        {
            return;
        }

        $group_ids = array();
        foreach ($members as $member)
        {
            $group_ids[] = $member->grp;
        }

        $qb = new midgard_query_builder('fi_openkeidas_groups_group');
        $qb->add_constraint('id', 'IN', $group_ids);
        $qb->add_order('metadata.score', 'DESC');

        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $group = $this->prepare_for_list($group);

            $mc = new midgard_collector('fi_openkeidas_diary_challenge_participant', 'grp', $group->id);
            //$mc->add_constraint('metadata.isapproved', '=', true);
            $mc->add_constraint('challenge.start', '<=', new DateTime());
            $mc->add_constraint('challenge.enddate', '>', new DateTime());
            $mc->set_key_property('challenge');
            $mc->execute();
            $challenge_ids = array_keys($mc->list_keys());
            if (count($challenge_ids) == 0)
            {
                $this->data['groups'][] = $group;
                continue;
            }
            $qb = new midgard_query_builder('fi_openkeidas_diary_challenge');
            $qb->add_constraint('id', 'IN', $challenge_ids);
            $group->challenges = array();
            $challenges = $qb->execute();
            foreach ($challenges as $challenge)
            {
                $challenge->url = midgardmvc_core::get_instance()->dispatcher->generate_url('challenge_read', array('challenge' => $challenge->guid), 'fi_openkeidas_diary');
                $group->challenges[] = $challenge;
            }

            $this->data['groups'][] = $group;
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
        $member_qb->add_constraint('metadata.isapproved', '=', true);
        $group->members = $member_qb->count();

        return $group;
    }
}
