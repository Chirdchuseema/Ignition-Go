<?php defined('BASEPATH') || exit('No direct script access allowed');
/**
 * Users Controller
 *
 * Frontend functions for users: register, profile et al.
 *
 */
class Users extends Front_Controller
{
    private $siteSettings;

    public function __construct()
    {
        parent::__construct();

        $this->load->helper('form');
        $this->load->library('form_validation');

        $this->load->model('users/user_model');

        $this->load->library('securinator/Auth');

        $this->lang->load('users');
        
        $this->siteSettings = $this->settings->find_all();
 
        if ($this->siteSettings['auth.password_show_labels'] == 1) {
            Assets::add_module_js('users', 'password_strength.js');
            Assets::add_module_js('users', 'jquery.strength.js');
        }
    }

    // -------------------------------------------------------------------------
    // User Management (Register/Update Profile)
    // -------------------------------------------------------------------------

    /**
     * Allow a user to edit their own profile information.
     *
     * @return void
     */
    public function profile()
    {
        // Make sure the user is logged in.
        $this->auth->restrict();
        $this->set_current_user();

        $this->load->helper('date');

        $this->load->config('countries');
        $this->load->helper('address');

        $this->load->config('user_meta');
        $meta_fields = config_item('user_meta_fields');

        Template::set('meta_fields', $meta_fields);

        if (isset($_POST['save'])) {
            $user_id = $this->current_user->id;
            if ($this->saveUser('update', $user_id, $meta_fields)) {
                $user = $this->user_model->find($user_id);
                $log_name = empty($user->display_name) ?
                    ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email)
                    : $user->display_name;

                // TODO log_activity
                //   lang('us_log_edit_profile')
                
                Template::set_message(lang('us_profile_updated_success'), 'success');

                // Redirect to make sure any language changes are picked up.
                Template::redirect('/users/profile');
            }

            Template::set_message(lang('us_profile_updated_error'), 'error');
        }

        // Get the current user information.
        $user = $this->user_model->find_user_and_meta($this->current_user->id);

        if ($this->siteSettings['auth.password_show_labels'] == 1) {
            Assets::add_js(
                $this->load->view('users_js', array('settings' => $this->siteSettings), true),
                'inline'
            );
        }

        // Generate password hint messages.
        $this->user_model->password_hints();

        Template::set('user', $user);
        Template::set('languages', array('' => '' ));// unserialize($this->settings_lib->item('site.languages')));

        Template::set_view('profile');
        Template::render();
    }

    /**
     * Display registration form for new user.
     *
     * @return void
     */
    public function register()
    {
        // Are users allowed to register?
        if (!$this->siteSettings['auth.allow_register']) {
            Template::set_message(lang('us_register_disabled'), 'error');
            Template::redirect('/');
        }

        $register_url = $this->input->post('register_url') ?: REGISTER_URL;
        $login_url    = $this->input->post('login_url') ?: LOGIN_URL;

        $this->load->helper('date');

        $this->load->config('countries');
        $this->load->helper('address');

        $this->load->config('user_meta');
        $meta_fields = config_item('user_meta_fields');
        Template::set('meta_fields', $meta_fields);

        Template::set('secarea', 'users');
        Template::set('secareatitleorlogo', 'Ignition Go');
        Template::set_view('register');
        Template::render();
    }

    
    /**
     * Display forgot password form for user.
     *
     * @return void
     */
    public function forgot()
    {
        $this->load->library('settings');
        $min_length = $this->settings->item('auth.password_min_length');
        $force_numbers = $this->settings->item('auth.password_force_numbers');
        Template::set('pw_min_length', $min_length);
        Template::set('pw_force_numbers', $force_numbers);
        Template::set('secarea', '');
        Template::set('secareatitleorlogo', 'Ignition Go');
        Template::set_view('securinator/auth/forgot');
        Template::render();
    }

    /**
     * Ajax for forgot password form for user.
     *
     * @return void
     */
    public function recover()
    {
        $ret = '';
        $em = $this->input->post('em1');

        $userrec = $this->user_model->find_by('email', $em);
        $banned = $userrec == null ? '' : $userrec->banned;

        // TODO - handle cases below as desired
        $msg = '';
        $border = 'red';

        if ($banned) {
            $msg = '<p>
				<strong>Account Locked or Disabled</strong>
			</p>
			<p>
				This account has been blocked or disabled by an administrator. 
				If you feel this is an error, you may contact us  
				to make an inquiry regarding the status of the account.
			</p> ';
        } elseif (isset($confirmation)) { /* only for testing, not production */
            $border='green';
            $msg = '<p>
				Congratulations, you have created an account recovery link.
			</p>
			<p>
				<b>Please <a href=\"' . $special_link . '\">click here</a> to reset your password.</b> 
            </p> ';
        } 
        elseif (isset($emailconfirm)) {
            $border='green';
            $msg = 'Check your email for instructions on how to recover your account.';
        } 
        else //if (isset($no_match)) 
        {
            $msg = 'An account with that email address could not be found.';

            $show_form = 1;
        }

        $ret .= 'msg:"'. $msg.'", border: "'.$border.'", showform: "'.$show_form.'"';
        echo('{'.$ret.'}');
            exit;
    }

    public function reset_password(){
        $newpw=$_POST['newpw'];
        $pw_hash=$this->auth->hash_password($newpw)['hash'];
        $this->db->where('email',$_POST['email']);
        $this->db->set('password_hash',$pw_hash);
        return $this->db->update('users');
    }
}
