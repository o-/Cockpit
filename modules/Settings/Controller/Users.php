<?php

namespace Settings\Controller;

use App\Controller\App;

class Users extends App {

    protected function before() {

        $isAccountView = $this->context['action'] == 'user' && !count($this->context['params']);

        if (!$isAccountView && !$this->isAllowed('app.users.manage')) {
            $this->stop(401);
        }
    }

    public function index() {

        return $this->render('settings:views/users/index.php');
    }

    public function user($id = null) {

        $isAccountView = !$id;

        if (!$id) {
            $id = $this->user['_id'];
        }

        $user = $this->app->dataStorage->findOne('system/users', ['_id' => $id]);

        if (!$user) {
            return false;
        }

        unset($user["password"]);

        return $this->render('settings:views/users/user.php', compact('user', 'isAccountView'));
    }

    public function create() {

        $user = [
            'active' => true,
            'user'   => '',
            'email'  => '',
            'role'  => 'admin',
            'i18n'   => $this->app->helper('i18n')->locale
        ];

        $isAccountView = false;

        return $this->render('settings:views/users/user.php', compact('user', 'isAccountView'));
    }

    public function save() {

        $user = $this->param('user');

        if (!$user) {
            return $this->stop(['error' => 'User data is missing'], 412);
        }

        $user['_modified'] = time();
        $isUpdate = isset($user['_id']);

        if (!$isUpdate) {

            // new user needs a password
            if (!isset($user['password']) || !trim($user['password'])) {
                return $this->stop(['error' => 'User password required'], 412);
            }

            if (!isset($user['user']) || !trim($user['user'])) {
                return $this->stop(['error' => 'Username required'], 412);
            }

            $user['_created'] = $user['_modified'];
        }

        if (isset($user['password'])) {

            if (strlen($user['password'])){
                $user['password'] = $this->app->hash($user['password']);
            } else {
                unset($user['password']);
            }
        }

        if (isset($user['email']) && !$this->helper('utils')->isEmail($user['email'])) {
            return $this->stop(['error' => 'Valid email required'], 412);
        }

        if (isset($user['user']) && !trim($user['user'])) {
            return $this->stop(['error' => 'Username cannot be empty!'], 412);
        }

        if (isset($user['name']) && !trim($user['name'])) {
            return $this->stop(['error' => 'Name cannot be empty!'], 412);
        }

        foreach (['name', 'user', 'email'] as $key) {
            $user[$key] = strip_tags(trim($user[$key]));
        }

        // unique check

        $_user = $this->app->dataStorage->findOne('system/users', ['user' => $user['user']]);

        if ($_user && (!isset($user['_id']) || $user['_id'] != $_user['_id'])) {
            $this->app->stop(['error' =>  'Username is already used!'], 412);
        }

        $_user = $this->app->dataStorage->findOne('system/users', ['email'  => $user['email']]);

        if ($_user && (!isset($user['_id']) || $user['_id'] != $_user['_id'])) {
            $this->app->stop(['error' =>  'Email is already used!'], 412);
        }
        // --

        $this->app->trigger('app.users.save', [&$user, $isUpdate]);
        $this->app->dataStorage->save('system/users', $user);

        $user = $this->app->dataStorage->findOne('system/users', ['_id' => $user['_id']]);

        unset($user['password'], $user['_reset_token']);

        if ($user['_id'] == $this->user['_id']) {
            $this->helper('auth')->setUser($user);
        }

        return $user;
    }

    public function remove() {

        $user = $this->param('user');

        if (!$user || !isset($user['_id'])) {
            return $this->stop(['error' => 'User is missing'], 412);
        }

        if ($user['_id'] == $this->user['_id']) {
            return $this->stop(['error' => "User can't delete himself"], 412);
        }

        $this->app->dataStorage->remove('system/users', ['_id' => $user['_id']]);

        return ['success' => true];
    }

    public function load() {

        \session_write_close();

        $options = array_merge([
            'sort'   => ['user' => 1]
        ], $this->param('options', []));

        if (isset($options['filter']) && is_string($options['filter'])) {

            $filter = null;

            if (\preg_match('/^\{(.*)\}$/', $options['filter'])) {

                try {
                    $filter = json5_decode($options['filter'], true);
                } catch (\Exception $e) {}
            }

            if (!$filter) {
                $filter = [
                    '$or' => [
                        ['name' => ['$regex' => $options['filter']]],
                        ['user' => ['$regex' => $options['filter']]],
                        ['email' => ['$regex' => $options['filter']]],
                    ]
                ];
            }

            $options['filter'] = $filter;
        }

        $users = $this->app->dataStorage->find('system/users', $options)->toArray();
        $count = (!isset($options['skip']) && !isset($options['limit'])) ? count($users) : $this->app->dataStorage->count('system/users', isset($options['filter']) ? $options['filter'] : []);
        $pages = isset($options['limit']) ? ceil($count / $options['limit']) : 1;
        $page  = 1;

        if ($pages > 1 && isset($options['skip'])) {
            $page = ceil($options['skip'] / $options['limit']) + 1;
        }

        foreach ($users as &$user) {
            $this->app->trigger('app.user.disguise', [&$user]);
        }

        return compact('users', 'count', 'pages', 'page');
    }

    public function getSecretQRCode($secret = null, $size = 150) {

        \session_write_close();

        if (!$secret) {
            return false;
        }

        $this->app->response->mime = 'svg';

        return $this->helper('twfa')->getQRCodeImage($secret, intval($size));
    }
}