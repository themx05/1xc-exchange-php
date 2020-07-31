<?php

use Core\MerchantProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$businessRouter = new Router();

$businessRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    return $res->json(['success' => false, 'requireAuth' => true]);
});

$businessRouter->post("/register", function(Request $req, Response $res){
    if($req->getOption('isAdmin')){
        return $res->json(['success' => false, 'requireAuth' => true]);
    }

    $user = $req->getOption('user');
    $merchantProvider = new MerchantProvider($req->getOption('storage'));

    $stored = $merchantProvider->getBusinessProfileByUser($user['id']);

    if(isset($stored)){
        return $res->json(['success' => false, 'message' => 'You already have a business profile']);
    }
    
    $upload_dir = $req->getOption('home')."/uploads";

    if(!is_dir($upload_dir)){
        mkdir($upload_dir,0775);
    }

    if(!isset($_POST['name'])){
        return $res->json(['success' => false, 'message' => 'merchant business name is required']);
    }

    if(!isset($_POST['country'])){
        return $res->json(['success' => false, 'message' => 'merchant country is required']);
    }

    if(!isset($_POST['city'])){
        return $res->json(['success' => false, 'message' => 'merchant city is required']);
    }

    if(!isset($_POST['phone'])){
        return $res->json(['success' => false, 'message' => 'merchant phone is required']);
    }

    if(!isset($_POST['email'])){
        return $res->json(['success' => false, 'message' => 'merchant email is required']);
    }

    if(!isset($_FILES['cni']) || empty($_FILES['cni']['tmp_name'])){
        return $res->json(['success' => false, 'message' => 'merchant cni is required']);
    }

    $business = [
        'name' => $_POST['name'],
        'country' => strtoupper($_POST['country']),
        'city' => protectString($_POST['city']),
        'phone' => protectString($_POST['phone']),
        'email' => protectString($_POST['email']),
        'documents' => []
    ];

    $allowed_extensions = array("jpeg","jpg","png", "pdf","docx","doc");

    $cni_data = array();
    $rc_data = array();
    $ifu_data = array();

    if(isset($_FILES['cni']) && !empty($_FILES['cni']['tmp_name'])){
        $cni = $_FILES['cni'];
        $uploadName = generateHash();
        $ext = strtolower(end(explode(".",$cni['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($cni['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'cni',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(['success' => false, 'message' => 'Failed to store cni']);
            }
        }else{
            return $res->json(['success' => false, 'message' => 'The cni file type provided is not allowed']);
        }
    }

    if(isset($_FILES['rc']) && !empty($_FILES['rc']['tmp_name'])){
        $rc = $_FILES['rc'];
        $uploadName = generateHash();
        $ext = strtolower(end(explode(".",$rc['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($rc['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'rc',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(['success' => false, 'message' => 'Failed to store rc']);
            }
        }
        else{
            return $res->json(['success' => false, 'message' => 'The rc file type provided is not allowed']);
        }
    }

    if(isset($_FILES['ifu']) && !empty($_FILES['ifu']['tmp_name'])){
        $ifu = $_FILES['ifu'];
        $uploadName = generateHash();
        $ext = strtolower(end(explode(".",$ifu['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($ifu['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'ifu',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(['success' => false,'message' => 'Failed to store ifu']);
            }
        }
        else{
            return $res->json(['success' => false, 'message' => 'The ifu file type provided is not allowed']);
        }
    }

    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    $businessId = $merchantProvider->createBusinessProfile($user['id'], $business);
    if(!empty($businessId)){
        return $res->json(['success' => true, 'businessId' => $businessId]);
    }
    else{
        return $res->json(['success' => false,'message' => 'failed to create business profile']);
    }
});

$businessRouter->get("/", function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    
    if($req->getOption('isAdmin')){
        $profiles = $merchantProvider->getProfiles();

        if(isset($profiles)){
            return $res->json(['success' => true, 'profiles' => $profiles]);
        }
    }
    else {
        $user = $req->getOption('user');
        $profile = $merchantProvider->getBusinessProfileByUser($user['id']);

        if(isset($profile)){
            return $res->json(['success' => true, 'profile' => $profile]);
        }
    }

    return $res->json(['success' => false]);
});

$singleBusiness = new Router();

$singleBusiness->get("/",function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    $business = $req->getParam('business');
    $profile = $merchantProvider->getProfileById($business);
    $user = $req->getOption('user');

    if($req->getOption('isAdmin') || $profile['userId'] === $user['id']){
        if(isset($profile)){
            return $res->json(['success' => true, 'profile' => $profile]);
        }
    }
    return $res->json(['success' => false]);
});

$singleBusiness->get("/approve", function(Request $req, Response $res){
    $client = $req->getOption('storage');
    if($client instanceof PDO){
        $merchantProvider = new MerchantProvider($client);
        $business = $req->getParam('business');
        $profile = $merchantProvider->getProfileById($business);
        $user = $req->getOption('user');

        if($req->getOption('isAdmin')){
            if(isset($profile)){
                $client->beginTransaction();
                $done = $merchantProvider->approveProfile($profile['id']);
                if($done){
                    $client->commit();
                    return $res->json(['success' => true]);
                }
                $client->rollBack();
            }
        }
    }
    return $res->json(['success' => false]);
});

$singleBusiness->patch("/documents/:docName/verified/:enable", function(Request $req, Response $res){
    $client = $req->getOption('storage');
    $enable = boolval($req->getOption('enable'));
    if($client instanceof PDO && $req->getOption('isAdmin')){
        $merchantProvider = new MerchantProvider($client);
        $business = $req->getParam('business');
        $docName = $req->getParam('docName');

        $profile = $merchantProvider->getProfileById($business);
        
        foreach($profile['documents'] as $key => $doc){
            if($doc['name'] == $docName){
                $doc['verified'] = $enable;
            }
            $profile['documents'][$key] = $doc;
        }
        $client->beginTransaction();
        $done = $merchantProvider->updateProfile($profile['id'], $profile);
        if($done){
            $client->commit();
            return $res->json(['success' => true]);
        }
        $client->rollBack();
    }
    return $res->json(['success' => false]);
});

$singleBusiness->delete("/", function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    $business = $req->getParam('business');
    $profile = $merchantProvider->getProfileById($business);
    $user = $req->getOption('user');

    if($req->getOption('isAdmin')){
        if(isset($profile)){
            $done = $merchantProvider->deleteProfile($profile['id']);
            if($done){
                return $res->json(['success' => true]);
            }
        }
    }
    return $res->json(['success' => false]);
});

$businessRouter->router("/:business", $singleBusiness);

global $application;
$application->router("/business", $businessRouter);

?>