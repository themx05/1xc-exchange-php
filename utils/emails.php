<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP;

    function sendVerificationEmail(string $user, string $emailAddress, string $code): bool{
        global $email;
        
        $builder = new StringBuilder(TEMPLATE_DIR."/email_validation.temp.html","fr");
        $builder->set("code",$code);
        $builder->set("user", $user);
        $builder->set("mail", $emailAddress);

        try{
            $mailer = new PHPMailer();
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
          //  $mailer->SMTPSecure = true;
            $mailer->Host = gethostname();
            $mailer->Port = $email['port'] ;
            $mailer->setFrom($email['user']);
            $mailer->Username = $email['user'];
            $mailer->Password = $email['password'];
            $mailer->addAddress($emailAddress);
            $mailer->Subject = "Validation de compte";
            $mailer->msgHTML($builder->render()); /// Replace with template 
            file_put_contents("./error_log", "Sending Email.".PHP_EOL, FILE_APPEND);
            file_put_contents("./error_log", "Server: ".json_encode($email).PHP_EOL, FILE_APPEND);
            $result = $mailer->send();
            file_put_contents("./error_log", "Email sent: ". ($result == true?1:0).PHP_EOL, FILE_APPEND);
            return $result;
        }catch(Exception $e){
            file_put_contents("./error_log", "Encountered Error: ".json_encode($e).PHP_EOL, FILE_APPEND);
            $result = $mailer->send();
            return false;
        }
    }

?>