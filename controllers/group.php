<?php
class fi_openkeidas_groups_controllers_group extends midgardmvc_core_controllers_baseclasses_crud
{
    public function load_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        if (!mgd_is_guid($args['group']))
        {
            throw new midgardmvc_exception_notfound("Group {$args['group']} not found");
        }

        try {
            $this->object = new fi_openkeidas_groups_group($args['group']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound($e->getMessage());
        }
    }
    
    public function prepare_new_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        $this->object = new fi_openkeidas_groups_group();
    }

    public function get_read(array $args)
    {
        parent::get_read($args);

        $this->data['admins'] = array();
        $this->data['is_admin'] = false;
        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('admin', '=', true);
        $qb->add_constraint('metadata.isapproved', '=', true);
        $admins = $qb->execute();
        foreach ($admins as $admin)
        {
            $this->data['admins'][] = new midgard_person($admin->person);
            if ($admin->person == midgardmvc_core::get_instance()->authentication->get_person()->id)
            {
                $this->data['is_admin'] = true;
            }
        }

        if ($this->data['is_admin'])
        {
            $this->data['members'] = array();
            $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
            $qb->add_constraint('grp', '=', $this->object->id);
            $qb->add_constraint('admin', '=', false);
            $members = $qb->execute();
            foreach ($members as $member)
            {
                $this->data['members'][] = new midgard_person($member->person);
            }

            $this->data['approve_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'group_join_approve', array
                (
                    'group' => $this->object->guid
                ),
                $this->request
            );
        }

        $this->data['is_member'] = $this->is_member();
        $this->data['join_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'group_join', array
            (
                'group' => $this->object->guid
            ),
            $this->request
        );
        $this->data['leave_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'group_leave', array
            (
                'group' => $this->object->guid
            ),
            $this->request
        );

        $this->data['challenges'] = array();
        $mc = new midgard_collector('fi_openkeidas_groups_group_member', 'person', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $mc->add_constraint('metadata.isapproved', '=', true);
        $mc->set_key_property('grp');
        $mc->execute();
        $grp_ids = array_keys($mc->list_keys());
        if (count($grp_ids) == 0)
        {
            return;
        }

        $qb = new midgard_query_builder('fi_openkeidas_diary_challenge');
        $qb->add_constraint('challenger', 'IN', $grp_ids);
        $qb->add_constraint('challenger', '<>', $this->object->id);
        $qb->add_constraint('enddate', '>', new DateTime());
        $challenges = $qb->execute();
        foreach ($challenges as $challenge)
        {
            $qb = new midgard_query_builder('fi_openkeidas_diary_challenge_participant');
            $qb->add_constraint('challenge', '=', $challenge->id);
            $qb->add_constraint('grp', '=', $this->object->id);
            if ($qb->count() > 0)
            {
                continue;
            }

            $challenge->url = midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'challenge_challenge', array
                (
                    'challenge' => $challenge->guid,
                    'group' => $this->object->guid,
                ),
                'fi_openkeidas_diary'
            );
            $this->data['challenges'][] = $challenge;
        }
    }

    private function is_member()
    {
        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_constraint('metadata.isapproved', '=', true);
        if ($qb->count() > 0)
        {
            return true;
        }
        return false;
    }

    public function post_join(array $args)
    {
        $this->load_object($args);

        if (!$this->is_member()
        {
            midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_groups');
            $member = new fi_openkeidas_groups_group_member();
            $member->person = midgardmvc_core::get_instance()->authentication->get_person()->id;
            $member->grp = $this->object->id;
            $member->admin = false;
            $member->create();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
        }

        midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
    }

    public function post_join_approve(array $args)
    {
        $this->load_object($args);

        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_constraint('admin', '=', true);
        $qb->add_constraint('metadata.isapproved', '=', true);
        $admins = $qb->execute();
        if (empty($admins))
        {
            throw new midgardmvc_exception_unauthorized("Not authorized to approve members");
        }

        if (!isset($_POST['member']))
        {
            throw new midgardmvc_exception_notfound("Specify which member to approve");
        }

        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('person', '=', $_POST['member']);
        $members = $qb->execute();
        if (empty($members))
        {
            throw new midgardmvc_exception_notfound("Member not found");
        }
        $approved = false;
        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_groups');
        foreach ($members as $member)
        {
            if ($approved) {
                // Kill duplicates
                $member->delete();
                continue;
            }
            $member->approve();
            $approved = true;
        }
        midgardmvc_core::get_instance()->authorization->leave_sudo();
        midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
    }

    public function post_leave(array $args)
    {
        $this->load_object($args);

        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $members = $qb->execute();
        foreach ($members as $member)
        {
            $member->delete();
        }

        midgardmvc_core::get_instance()->head->relocate
        (
            midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'index', array(), $this->request
            )
        );
    }

    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('fi_openkeidas_groups_group');
        $title = $this->form->add_field('title', 'text', true);
        $title_widget = $title->set_widget('text');
        $title_widget->set_label('Nimi');
    }

    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'group_read', array
            (
                'group' => $this->object->guid
            ),
            $this->request
        );
    }

    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'group_delete', array
            (
                'group' => $this->object->guid
            ),
            $this->request
        );
    }
}
