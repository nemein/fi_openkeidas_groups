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
        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('grp', '=', $this->object->id);
        $qb->add_constraint('admin', '=', true);
        $qb->add_constraint('metadata.isapproved', '=', true);
        $admins = $qb->execute();
        foreach ($admins as $admin)
        {
            $this->data['admins'][] = new midgard_person($admin->person);
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

        $member = new fi_openkeidas_groups_group_member();
        $member->person = midgardmvc_core::get_instance()->authentication->get_person()->id;
        $member->grp = $this->object->id;
        $member->admin = false;
        $member->create();

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
        $title = $this->form->add_field('title', 'text');
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
