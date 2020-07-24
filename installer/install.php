<?php
    include_once("../bootstrap.php");

    if(isset($_POST['configure'])){
        echo "true";
    }

    function handleConfiguration(){
        $base_install_dir = '../store/';

        $database = parseDatabase();
        $admin = parseAdmin();
    }

    function parseDatabase(){
        $host="localhost";
        $port=3306;
        $user = "root";
        $password = "";
        
        if(isset($_POST['db.host'])){
            $host = $_POST['db.host'];
        }
        if(isset($_POST['db.port'])){
            $port = intval($_POST['db.port']);
        }
        if(isset($_POST['db.user'])){
            $user = $_POST['db.user'];
        }
        if(isset($_POST['db.password'])){
            $password = $_POST['db.password'];
        }

        return [
            'host' =>$host,
            'port' => $port,
            'user' => $user,
            $password => $password
        ];
    }

    function parseAdmin(){
        $host="localhost";
        $port=3306;
        $user = "root";
        $password = "";
        
        if(isset($_POST['db.host'])){
            $host = $_POST['db.host'];
        }
        if(isset($_POST['db.port'])){
            $port = intval($_POST['db.port']);
        }
        if(isset($_POST['db.user'])){
            $user = $_POST['db.user'];
        }
        if(isset($_POST['db.password'])){
            $password = $_POST['db.password'];
        }

        return [
            'host' =>$host,
            'port' => $port,
            'user' => $user,
            $password => $password
        ];
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="./generic.css">
    <link rel="stylesheet" href="./style.css">
    <title>1xCrypto</title>
</head>
<body>
    <div class="rd-grid rd-gap-5" id="root">
        <header class="rd-xs-12 w-100 header">
            <span class="text-headline-5">Configuration 1xCrypto</span>
        </header>
        <div class="rd-xs-12 container">
            <form action="" method="POST" class="rd-xs-12 rd-grid rd-gap-5">
                <div class="rd-xs-12 rd-grid rd-gap-5">
                    <span class="rd-xs-12 text-headline-6 blue-500">Base de données</span>
                    <div class="rd-xs-12 form-group">
                        <label for="">Hote</label>
                        <input type="text" class="w-100" placeholder="Hote" name="db.host" value="localhost">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Port</label>
                        <input type="text" class="w-100" placeholder="Port" name="db.port" value="3306">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Nom d'utilisateur</label>
                        <input type="text" class="w-100" placeholder="Nom d'utilisateur" name="db.user" value="root">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Mot de passe</label>
                        <input type="text" class="w-100" placeholder="Mot de passe" name="db.password" value="root">
                    </div>
                </div>
                <div class="rd-xs-12 rd-grid rd-gap-5">
                    <span class="rd-xs-12 text-headline-6 blue-500">Email</span>
                    <div class="rd-xs-12 form-group">
                        <label for="">Adresse</label>
                        <input type="text" class="w-100" placeholder="Adresse" name="email.host" value="stmp://localhost">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Port</label>
                        <input type="text" class="w-100" placeholder="Port" name="email.host" value="465">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Utilisateur</label>
                        <input type="text" class="w-100" placeholder="Nom d'utilisateur" name="email.user">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Mot de passe</label>
                        <input type="text" class="w-100" type="password" placeholder="Mot de passe" name="email.password" value="root">
                    </div>
                </div>
                <div class="rd-xs-12 rd-grid rd-gap-5">
                    <span class="rd-xs-12 text-headline-6 blue-500">Administrateur initial</span>
                    <div class="rd-xs-12 form-group">
                        <label for="">Nom</label>
                        <input type="text" class="w-100" placeholder="Nom" name="admin.lastname" value="admin">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Prénoms</label>
                        <input type="text" class="w-100" placeholder="Prenom" name="admin.firstname" value="admin">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Utilisateur</label>
                        <input type="text" class="w-100" placeholder="Nom d'utilisateur" name="admin.alias" value="root">
                    </div>
                    <div class="rd-xs-12 form-group">
                        <label for="">Mot de passe</label>
                        <input type="text" class="w-100" placeholder="Mot de passe" name="admin.password" value="root">
                    </div>
                </div>
                <div class="rd-xs-12 submit-wrapper">
                    <input type="submit" name="configure" value="Configurer le systeme" class="submit bg-blue-500">
                </div>
            </form>
        </div>
    </div>
</body>
</html>