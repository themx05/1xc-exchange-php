<?php

use Core\SystemAdminProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$adminRouter = new Router();

$adminRouter->get("/signin", function(Request $req, Response $res){
    if($req->getOption('connected') && $req->getOption('isAdmin')){
        return $res->json(['success' => true]);
    }
    return $res->json(['success' => false, 'requireAuth' => true]);
});

$adminRouter->post("/signin", function(Request $req, Response $res){
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $data = $req->getOption('body');
    $result = $adminProvider->getAdminByCredential($data);
    if(isset($result)){
        logAdmin($result);
        return $res->json(['success' => true]);
    }else{
        $rootAdmin = $adminProvider->getRootAdmin();
        if(!isset($rootAdmin) || !isset($rootAdmin['id'])){
            $std_admin = new stdClass;
            $std_admin->{'firstname'} = 'Root';
            $std_admin->{'lastname'} = 'Root';
            $std_admin->{'alias'} = $data->alias;
            $std_admin->{'password'} = $data->password;

            $created_id = $adminProvider->createAdmin($std_admin);
            if(!empty($created_id)){
                $created = $adminProvider->getAdminById($created_id);
                logAdmin($created);
                return $res->json(['success' => true]);
            }
        }
    }
    return $res->json(['success' => false]);
});

$adminRouter->post("/signup", function(Request $req, Response $res){
    $data = $req->getOption('body');
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $id = $adminProvider->createAdmin($data);

    if(!empty($id)){
        return $res->json(['success' => true]);
    }
    return $res->json(['success' => false]);
});

$adminRouter->global(function(Request $req, Response $res, Closure $next){
    //Except the preivous routes, if you are not connected, you can't access the next
    if($req->getOption('connected') && $req->getOption('isAdmin')){
        $next();
    }
    else{
        return $res->json([
            'success' => false,
            'requireAuth' => true
        ]);
    }
});

$adminRouter->get("/", function(Request $req, Response $res){
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $admins = $adminProvider->getAdmins();

    if(isset($admins)){
        return $res->json(['success' => true, 'admins'=>$admins]);
    }
    return $res->json(['success' => false]);
});

$adminRouter->get("/profile", function(Request $req, Response $res){
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $admin = $adminProvider->getAdminById($req->getOption('user')['id']);

    if(!empty($admin)){
        return $res->json(['success' => true, 'admin'=>$admin]);
    }
    return $res->json(['success' => false]);
});

$adminRouter->patch("/profile",function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(isset($data->firstName) && isset($data->lastName) && isset($data->gender)){
        $adminProvider = new SystemAdminProvider($req->getOption('storage'));
        $userId = $req->getOption('user')['id'];
        $done = $adminProvider->updateProfile($userId, $data);
        if($done){
            return $res->json(['success' => true]);
        }
    }
    return $res->json([
        'success' => false
    ]);
});
$adminRouter->patch("/credentials",function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(isset($data->alias) &&  isset($data->lastPassword) && isset($data->newPassword)){
        $adminProvider = new SystemAdminProvider($req->getOption('storage'));
        $userId = $req->getOption('user')['id'];

        $done = $adminProvider->updatePassword($userId, $data);
        if($done){
            return $res->json(['success' => true]);
        }
    }
});

global $application;
$application->router("/admins", $adminRouter);
?>