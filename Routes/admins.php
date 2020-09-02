<?php

use Core\SystemAdminProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use \Firebase\JWT\JWT;
use Models\User;

$adminRouter = new Router();

$adminRouter->get("/signin", function(Request $req, Response $res){
    if($req->getOption('connected') && $req->getOption('isAdmin')){
        return $res->json(buildSuccess(true));
    }
    return $res->json(buildErrors([],['requireAuth' => true]));
});

$adminRouter->post("/signin", function(Request $req, Response $res){
    $propertiesProvider = new \Core\SystemProperties($req->getOption('storage'));
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $authentication = $propertiesProvider->getSystemProperties()->authentication;
    $data = $req->getOption('body');
    $result = $adminProvider->getAdminByCredential($data);
    if($result !== null){
        
        $payload = [
            'iss' => 'https://api.1xcrypto.net',
            'iat' => time(),
            'exp' => time() + 86400 * 3,
            'scope' => 'administrator',
            'user' => $result->id,
            'firstName' => $result->firstNam,
            'lastName' => $result->lastName
        ];

        $token = JWT::encode($payload, $authentication->secret);
        setcookie('token', $token, [
            'expires' => time() + 86400 * 3
        ]);
        return $res->json(buildSuccess($token));
        
    }else{
        $rootAdmin = $adminProvider->getRootAdmin();
        if(!isset($rootAdmin) || !isset($rootAdmin['id'])){
            $std_admin = new stdClass;
            $std_admin->firstname = 'Root';
            $std_admin->lastname = 'Root';
            $std_admin->alias = $data->alias;
            $std_admin->password = $data->password;

            $created_id = $adminProvider->createAdmin($std_admin);
            if(!empty($created_id)){

                $created = $adminProvider->getAdminById($created_id);
                if($created !== null){
                    $payload = [
                        'iss' => 'https://api.1xcrypto.net',
                        'iat' => time(),
                        'exp' => time() + 86400 * 3,
                        'scope' => 'administrator',
                        'user' => $created->id,
                        'firstName' => $created->firstName,
                        'lastName' => $created->lastName
                    ];
            
                    $token = JWT::encode($payload, $authentication->secret);
                    setcookie('token', $token, [
                        'expires' => time() + 86400 * 3
                    ]);
                    return $res->json(buildSuccess($token));
                }
            }
        }
    }
    return $res->json(buildErrors());
});

$adminRouter->post("/signup", function(Request $req, Response $res){
    $data = $req->getOption('body');

    /// Route currently disabled.
    return $res->json(buildErrors());

    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $id = $adminProvider->createAdmin($data);

    if(!empty($id)){
        return $res->json(buildSuccess());
    }
    return $res->json(buildErrors());
});

$adminRouter->global(function(Request $req, Response $res, Closure $next){
    //Except the preivous routes, if you are not connected, you can't access the next
    if($req->getOption('connected') && $req->getOption('isAdmin')){
        $next();
    }
    else{
        return $res->json(buildErrors([],['requireAuth' => true]));
    }
});

$adminRouter->get("/", function(Request $req, Response $res){
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $admins = $adminProvider->getAdmins();
    return $res->json(buildSuccess($admins));
});

$adminRouter->get("/profile", function(Request $req, Response $res){
    $adminProvider = new SystemAdminProvider($req->getOption('storage'));
    $admin = $adminProvider->getAdminById($req->getOption('user')['id']);

    if($admin !== null){
        return $res->json(buildSuccess($admin));
    }
    return $res->json(buildErrors());
});

$adminRouter->patch("/profile",function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(isset($data->firstName) && isset($data->lastName) && isset($data->gender)){
        $adminProvider = new SystemAdminProvider($req->getOption('storage'));
        $userId = $req->getOption('user')['id'];
        $admin = $adminProvider->getAdminById($userId);
        if($admin !==null){
            $admin->firstName = protectString($data->firstName);
            $admin->lastName = protectString($data->lastName);
            $admin->gender = User::isGenderValid($data->gender) ? $data->gender : User::GENDER_MALE;
        }
        $done = $adminProvider->updateProfile($admin);
        if($done){
            return $res->json(buildSuccess($done));
        }
    }
    return $res->json(buildErrors());
});

$adminRouter->patch("/credentials",function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(isset($data->alias) &&  isset($data->lastPassword) && isset($data->newPassword)){
        $adminProvider = new SystemAdminProvider($req->getOption('storage'));
        $userId = $req->getOption('user')['id'];
        $admin = $adminProvider->getAdminById($userId);
        if($admin !==null){
            if($admin->passwordHash !== $adminProvider->hashPassword($data->lastPassword)){
                return $res->json(buildErrors(['lastPassword' => 'Wrong password']));
            }
            $admin->passwordHash = $data->newPassword;
            $admin->updatedAt = time();
        }
        $done = $adminProvider->updatePassword($admin);
        if($done){
            return $res->json(buildSuccess());
        }
    }
    return $res->json(buildErrors());
});

global $application;
$application->router("/admins", $adminRouter);
?>